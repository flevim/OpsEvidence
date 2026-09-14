from __future__ import annotations

import json
import os

import pytest
import responses

from opsevidence_agent import cli, logging_setup
from opsevidence_agent import config as config_module
from opsevidence_agent.client import EvidenceClient, EvidenceClientError
from opsevidence_agent.collectors import disks, docker, host, system, updates
from opsevidence_agent.config import AgentConfig, ConfigError, load_config, write_config_file
from opsevidence_agent.payload import build_payload, evidence_item, validate_evidence


def test_redact_masks_token() -> None:
    assert logging_setup.redact("hola ops_abcdefgh1234567890 chau") == "hola ops_**** chau"
    assert logging_setup.redact("sin token") == "sin token"


def test_env_overrides_file(monkeypatch, tmp_path) -> None:
    cfg = tmp_path / "agent.yml"
    cfg.write_text("endpoint: https://file.example\nasset: file-asset\n", encoding="utf-8")

    monkeypatch.setenv("OPSEVIDENCE_ENDPOINT", "https://env.example")
    monkeypatch.setenv("OPSEVIDENCE_CONFIG", str(cfg))

    config = load_config({})

    assert config.endpoint == "https://env.example"
    assert config.asset == "file-asset"


def test_cli_overrides_env(monkeypatch) -> None:
    monkeypatch.setenv("OPSEVIDENCE_ENDPOINT", "https://env.example")

    config = load_config({"endpoint": "https://cli.example"})

    assert config.endpoint == "https://cli.example"


def test_read_config_file_rejects_nested(tmp_path) -> None:
    cfg = tmp_path / "agent.yml"
    cfg.write_text("endpoint: https://x\n  nested: true\n", encoding="utf-8")

    with pytest.raises(ConfigError):
        config_module.read_config_file(cfg)


def test_require_endpoint_and_token() -> None:
    with pytest.raises(ConfigError):
        AgentConfig().require_endpoint()

    with pytest.raises(ConfigError):
        AgentConfig(endpoint="ftp://x").require_endpoint()

    with pytest.raises(ConfigError):
        AgentConfig(endpoint="https://x").require_token()


@pytest.mark.skipif(os.name == "nt", reason="permisos POSIX no aplican en Windows")
def test_write_config_file_permissions(tmp_path) -> None:
    path = write_config_file(tmp_path / "agent.yml", {"endpoint": "https://x", "token": "ops_abc12345678"})

    assert (path.stat().st_mode & 0o777) == 0o600


def test_evidence_item_shape() -> None:
    item = evidence_item("DISK_USAGE", data={"mountpoint": "/", "used_percent": 90.0}, value_numeric=90.0, unit="%")

    assert item == {
        "type": "DISK_USAGE",
        "data": {"mountpoint": "/", "used_percent": 90.0},
        "value_numeric": 90.0,
        "unit": "%",
    }


def test_build_payload_caps_items() -> None:
    config = AgentConfig(asset="web-01")
    evidence = [evidence_item("AGENT_HEARTBEAT")] * 250

    payload = build_payload(config, evidence)

    assert len(payload["evidence"]) == 200
    assert payload["asset"] == "web-01"
    assert payload["agent_version"] == "1.0.0"
    assert "collected_at" in payload


def test_validate_evidence_rejects_bad_type() -> None:
    with pytest.raises(ValueError):
        validate_evidence([{"type": "NO_EXISTE"}])


@responses.activate
def test_client_send_ok() -> None:
    config = AgentConfig(endpoint="https://ops.example.com", token="ops_secret_token_12345678")

    responses.add(
        responses.POST,
        "https://ops.example.com/api/agent/evidence",
        json={"accepted": 3},
        status=202,
    )

    result = EvidenceClient(config).send({"evidence": []})

    assert result == {"accepted": 3}


@responses.activate
def test_client_retries_on_500(monkeypatch) -> None:
    import opsevidence_agent.client as client_module

    monkeypatch.setattr(client_module, "_backoff", lambda attempt: 0)

    config = AgentConfig(endpoint="https://ops.example.com", token="ops_secret_token_12345678")

    responses.add(responses.POST, "https://ops.example.com/api/agent/evidence", status=500)
    responses.add(
        responses.POST,
        "https://ops.example.com/api/agent/evidence",
        json={"accepted": 1},
        status=202,
    )

    result = EvidenceClient(config).send({"evidence": []})

    assert result == {"accepted": 1}
    assert len(responses.calls) == 2


@responses.activate
def test_client_does_not_retry_on_401() -> None:
    config = AgentConfig(endpoint="https://ops.example.com", token="ops_secret_token_12345678")

    responses.add(responses.POST, "https://ops.example.com/api/agent/evidence", status=401)

    with pytest.raises(EvidenceClientError) as excinfo:
        EvidenceClient(config).send({"evidence": []})

    assert "ops_secret_token_12345678" not in str(excinfo.value)
    assert len(responses.calls) == 1


def test_collect_system_assembles(monkeypatch) -> None:
    monkeypatch.setattr(system, "_read_uptime", lambda: 1000.0)
    monkeypatch.setattr(system, "_read_loadavg", lambda: (0.1, 0.2, 0.3))
    monkeypatch.setattr(system, "_read_cpu_percent", lambda interval=1.0: 12.5)
    monkeypatch.setattr(
        system,
        "_read_memory",
        lambda: {"total_bytes": 100, "used_bytes": 40, "available_bytes": 60, "used_percent": 40.0},
    )

    evidence = system.collect_system()
    types = {item["type"] for item in evidence}

    assert types == {"SERVER_UPTIME", "CPU_USAGE", "MEMORY_USAGE"}


def test_read_memory_from_fixture(tmp_path, monkeypatch) -> None:
    proc = tmp_path / "meminfo"
    proc.write_text(
        "MemTotal: 8000000 kB\nMemAvailable: 4000000 kB\nSwapTotal: 2000000 kB\nSwapFree: 1000000 kB\n",
        encoding="utf-8",
    )
    monkeypatch.setattr(system, "PROC_MEMINFO", str(proc))

    result = system._read_memory()

    assert result is not None
    assert result["used_percent"] == 50.0
    assert result["total_bytes"] == 8000000 * 1024
    assert result["swap_total_bytes"] == 2000000 * 1024


def test_host_info_from_fixture(tmp_path, monkeypatch) -> None:
    release = tmp_path / "os-release"
    release.write_text('NAME="Debian"\nPRETTY_NAME="Debian GNU/Linux 12"\n', encoding="utf-8")
    monkeypatch.setattr(host, "OS_RELEASE_PATH", str(release))

    evidence = host.collect_host_info()

    assert evidence[0]["type"] == "HOST_INFO"
    assert evidence[0]["data"]["os"] == "Debian GNU/Linux 12"


def test_disks_skips_pseudo(monkeypatch, tmp_path) -> None:
    mounts = tmp_path / "mounts"
    mounts.write_text(
        "/dev/sda1 / ext4 rw,relatime 0 0\nproc /proc proc rw 0 0\ntmpfs /run tmpfs rw 0 0\n",
        encoding="utf-8",
    )
    monkeypatch.setattr(disks, "PROC_MOUNTS", str(mounts))
    monkeypatch.setattr(
        disks,
        "_usage",
        lambda mountpoint: {"total": 100, "used": 90, "free": 10, "used_percent": 90.0},
    )

    evidence = disks.collect_disks()

    assert len(evidence) == 1
    assert evidence[0]["data"]["mountpoint"] == "/"


def test_disks_produces_one_item_per_real_filesystem(monkeypatch, tmp_path) -> None:
    mounts = tmp_path / "mounts"
    mounts.write_text(
        "/dev/sda1 / ext4 rw,relatime 0 0\n"
        "/dev/sdb1 /var ext4 rw,relatime 0 0\n",
        encoding="utf-8",
    )
    monkeypatch.setattr(disks, "PROC_MOUNTS", str(mounts))
    monkeypatch.setattr(
        disks,
        "_usage",
        lambda mountpoint: {"total": 100, "used": 50, "free": 50, "used_percent": 50.0},
    )

    evidence = disks.collect_disks()

    assert [item["data"]["mountpoint"] for item in evidence] == ["/", "/var"]


def test_disks_skips_container_and_vm_shares(monkeypatch, tmp_path) -> None:
    # Un bind mount de Windows aparece como 9p dentro del contenedor y se
    # reportaba como un disco mas.
    mounts = tmp_path / "mounts"
    mounts.write_text(
        "/dev/sda1 / ext4 rw,relatime 0 0\n"
        "C:\\134 /src 9p rw,relatime 0 0\n"
        "hostshare /shared virtiofs rw,relatime 0 0\n"
        "user@host:/ /mnt/remoto fuse.sshfs rw,relatime 0 0\n"
        "proc /proc proc rw 0 0\n",
        encoding="utf-8",
    )
    monkeypatch.setattr(disks, "PROC_MOUNTS", str(mounts))
    monkeypatch.setattr(
        disks,
        "_usage",
        lambda mountpoint: {"total": 100, "used": 90, "free": 10, "used_percent": 90.0},
    )

    evidence = disks.collect_disks()

    assert len(evidence) == 1
    assert evidence[0]["data"]["mountpoint"] == "/"


def test_docker_produces_two_types(monkeypatch) -> None:
    containers = [{"name": "web", "state": "running", "health": "healthy", "restart_count": 0}]
    monkeypatch.setattr(docker, "_inspect_containers", lambda _: containers)

    evidence = docker.collect_docker()
    types = {item["type"] for item in evidence}

    assert types == {"DOCKER_CONTAINER_STATUS", "DOCKER_HEALTH"}


def test_docker_summarize() -> None:
    line = json.dumps(
        {
            "Id": "abc123def456",
            "Name": "/web",
            "State": {"Status": "running", "Health": {"Status": "healthy"}},
            "RestartCount": 3,
            "Config": {"Image": "nginx:latest"},
            "HostConfig": {"RestartPolicy": {"Name": "unless-stopped"}},
        },
    )

    result = docker._summarize(line)

    assert result is not None
    assert result["name"] == "web"
    assert result["health"] == "healthy"
    assert result["restart_count"] == 3
    assert result["restart_policy"] == "unless-stopped"


def test_docker_summarize_defaults_restart_policy_to_no() -> None:
    # Sin HostConfig, el contenedor no se considera "debe estar corriendo".
    line = json.dumps({"Name": "/one-shot", "State": {"Status": "exited"}, "Config": {"Image": "alpine"}})

    result = docker._summarize(line)

    assert result is not None
    assert result["restart_policy"] == "no"


def test_apt_updates(monkeypatch) -> None:
    monkeypatch.setattr(updates.shutil, "which", lambda name: "/usr/bin/apt-get" if name == "apt-get" else None)

    class Result:
        stdout = "Inst nginx [1.2]\nInst openssl [1.1] (security)\n0 upgraded\n"

    monkeypatch.setattr(updates.subprocess, "run", lambda *args, **kwargs: Result())

    evidence = updates.collect_pending_updates()

    assert evidence[0]["data"]["total_count"] == 2
    assert evidence[0]["data"]["security_count"] == 1


def test_collect_dry_run(monkeypatch, capsys) -> None:
    monkeypatch.setattr(cli, "collect_all", lambda: [])

    code = cli.main(["collect", "--dry-run", "--json", "--asset", "web-01"])

    payload = json.loads(capsys.readouterr().out)

    assert code == 0
    assert payload["asset"] == "web-01"
    assert payload["evidence"][0]["type"] == "AGENT_HEARTBEAT"


def test_collect_requires_token(monkeypatch) -> None:
    monkeypatch.setattr(cli, "collect_all", lambda: [])

    code = cli.main(["collect", "--endpoint", "https://ops.example.com"])

    assert code == cli.EXIT_CONFIG


def test_check_config_redacts_token(monkeypatch, capsys) -> None:
    monkeypatch.setenv("OPSEVIDENCE_ENDPOINT", "https://ops.example.com")
    monkeypatch.setenv("OPSEVIDENCE_TOKEN", "ops_secret_token_12345678")

    code = cli.main(["check-config"])

    out = capsys.readouterr().out

    assert code == 0
    assert "ops_****" in out
    assert "ops_secret_token_12345678" not in out


def test_show_collectors(capsys) -> None:
    code = cli.main(["show-collectors"])

    assert code == 0
    assert "system" in capsys.readouterr().out

import { expect, test } from '@playwright/test'

test('el entorno recién levantado permite entrar y navegar por el MVP', async ({ page }) => {
  await page.goto('/')

  await expect(page).toHaveURL(/\/login/)
  await expect(page.getByRole('heading', { name: 'OpsEvidence' })).toBeVisible()

  await page.getByLabel('Correo electrónico').fill('owner@opsevidence.test')
  await page.getByLabel('Contraseña').fill('password')
  await page.getByRole('button', { name: 'Iniciar sesión' }).click()

  await expect(page).toHaveURL(/\/$/)
  await expect(page.getByRole('heading', { name: 'Panel' })).toBeVisible()
  await expect(page.getByText('Ocurrió un error inesperado.')).toHaveCount(0)

  await page.getByRole('link', { name: 'Clientes' }).click()
  await expect(page).toHaveURL(/\/clients$/)
  await expect(page.getByRole('heading', { name: 'Clientes' })).toBeVisible()

  await page.locator('tbody a').first().click()
  await expect(page.getByRole('heading', { name: 'Ambientes' })).toBeVisible()

  await page.getByRole('link', { name: 'Usuarios' }).click()
  await expect(page).toHaveURL(/\/users$/)
  await expect(page.getByRole('heading', { name: 'Usuarios' })).toBeVisible()

  await page.getByRole('link', { name: 'Informes' }).click()
  await expect(page).toHaveURL(/\/reports$/)
  await expect(page.getByRole('heading', { name: 'Informes' })).toBeVisible()
})

<script setup lang="ts"></script>

<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="6" class="text-center">
        <v-icon size="56" color="grey">mdi-map-marker-question-outline</v-icon>
        <h1 class="text-h6 font-weight-bold mt-4">Página no encontrada</h1>
        <p class="text-body-2 text-medium-emphasis">La dirección que abriste no existe.</p>
        <v-btn color="primary" class="mt-2" to="/">Volver al panel</v-btn>
      </v-col>
    </v-row>
  </v-container>
</template>

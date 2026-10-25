<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps({
    status: { type: Number, required: true },
});

const page = usePage();

const messages = {
    403: ['Accès refusé', "Vous n'avez pas la permission d'accéder à cette page dans la société actuelle."],
    404: ['Page introuvable', "Cette page n'existe pas ou a été supprimée."],
    500: ['Erreur serveur', 'Une erreur est survenue. Veuillez réessayer.'],
    503: ['Service indisponible', 'Maintenance en cours, revenez dans quelques instants.'],
};

const content = computed(() => messages[props.status] ?? messages[500]);
</script>

<template>
    <Head :title="content[0]" />
    <div class="flex min-h-svh flex-col items-center justify-center gap-2 p-6 text-center">
        <p class="text-sm font-semibold text-muted-foreground">{{ status }}</p>
        <h1 class="text-2xl font-semibold tracking-tight">{{ content[0] }}</h1>
        <p class="max-w-md text-sm text-muted-foreground">{{ content[1] }}</p>
        <Button as-child class="mt-4">
            <Link :href="page.props.auth?.user ? route('dashboard') : route('login')">Retour à l'accueil</Link>
        </Button>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post(route('login.store'), {
    onFinish: () => form.reset('password'),
});
</script>

<template>
    <GuestLayout title="Connexion">
        <Card>
            <CardHeader class="text-center">
                <CardTitle class="text-xl">Connexion</CardTitle>
                <CardDescription>Accédez à votre espace de gestion.</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-5" @submit.prevent="submit">
                    <FormField id="email" v-model="form.email" label="E-mail" type="email" autocomplete="username"
                        placeholder="nom@societe.ma" required :error="form.errors.email" />
                    <FormField id="password" v-model="form.password" label="Mot de passe" type="password"
                        autocomplete="current-password" required :error="form.errors.password" />

                    <div class="flex items-center gap-2">
                        <Checkbox id="remember" v-model="form.remember" />
                        <Label for="remember" class="font-normal">Se souvenir de moi</Label>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Se connecter
                    </Button>
                </form>
            </CardContent>
        </Card>
    </GuestLayout>
</template>

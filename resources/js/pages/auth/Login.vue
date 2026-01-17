<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Prihlásenie" />

    <div class="min-h-svh bg-neutral-950 text-white">
        <div class="grid min-h-svh lg:grid-cols-[1.1fr_0.9fr]">
            <div
                class="relative hidden overflow-hidden bg-neutral-900 lg:flex"
            >
                <div
                    class="absolute inset-0 bg-cover bg-center"
                    style="background-image: url('/assets/images/login-bg.jpg')"
                ></div>
                <div class="absolute inset-0 bg-gradient-to-br from-black/80 via-black/70 to-black/60"></div>
                <div class="absolute inset-0 opacity-50 [background:radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.35),transparent_45%),radial-gradient(circle_at_80%_80%,rgba(99,102,241,0.4),transparent_50%)]"></div>

                <div class="relative z-10 flex w-full flex-col justify-start p-10 xl:p-14">
                    <div class="max-w-xl space-y-6">
                        <div class="space-y-0">
                            <h1 class="text-4xl font-semibold leading-tight xl:text-5xl">
                                Perún
                            </h1>
                            <p class="text-base text-white/80 xl:text-lg">
                                Strážca času a pamäti
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center bg-white px-6 py-10 text-neutral-900">
                <div class="w-full max-w-md space-y-8">
                    <div class="space-y-2">
                        <h2 class="text-2xl font-semibold">
                            Vitajte v systéme Perún
                        </h2>
                        <p class="text-sm text-neutral-500">
                            Pre prihlásenie do systému zadajte svoju
                            mailovú adresu a heslo.
                        </p>
                    </div>

                    <div
                        v-if="status"
                        class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
                    >
                        {{ status }}
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="flex flex-col gap-6"
                    >
                        <div class="grid gap-5">
                            <div class="grid gap-2">
                                <Label for="email">E‑mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="email"
                                    placeholder="meno@domena.sk"
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <div class="grid gap-2">
                                <div class="flex items-center justify-between">
                                    <Label for="password">Heslo</Label>
                                    <a
                                        v-if="canResetPassword"
                                        :href="request()"
                                        class="text-xs font-medium text-neutral-500 underline underline-offset-4"
                                        :tabindex="5"
                                    >
                                        Zabudnuté heslo?
                                    </a>
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="flex items-center justify-between">
                                <Label for="remember" class="flex items-center space-x-3">
                                    <Checkbox id="remember" name="remember" :tabindex="3" />
                                    <span class="text-sm text-neutral-600">Zapamätať si</span>
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                class="mt-2 w-full bg-indigo-600 text-white hover:bg-indigo-500"
                                :tabindex="4"
                                :disabled="processing"
                                data-test="login-button"
                            >
                                <Spinner v-if="processing" />
                                Prihlásiť sa
                            </Button>
                        </div>
                    </Form>
                </div>
            </div>
        </div>
    </div>
</template>

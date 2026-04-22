<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Lock, Loader2, KeyRound } from 'lucide-vue-next'
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/Components/ui/form'

const props = defineProps({
    email: String,
    token: String,
})

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Resetowanie hasła" />

        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-3xl font-bold tracking-tight text-foreground">Ustaw nowe hasło</h1>
                <p class="text-lg text-muted-foreground">
                    Twoje hasło musi mieć co najmniej 8 znaków.
                </p>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-5">
                <input type="hidden" v-model="form.token" />
                
                <FormField name="email">
                    <FormItem class="flex flex-col gap-2">
                        <FormLabel>Email</FormLabel>
                        <FormControl>
                            <Input 
                                type="email" 
                                v-model="form.email" 
                                readonly
                                class="bg-muted/50"
                            />
                        </FormControl>
                        <FormMessage>{{ form.errors.email }}</FormMessage>
                    </FormItem>
                </FormField>

                <FormField name="password">
                    <FormItem class="flex flex-col gap-2">
                        <FormLabel>Nowe hasło</FormLabel>
                        <FormControl>
                            <div class="relative">
                                <Lock class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                <Input 
                                    type="password" 
                                    v-model="form.password" 
                                    class="pl-10"
                                    required 
                                    autocomplete="new-password"
                                    autofocus
                                />
                            </div>
                        </FormControl>
                        <FormMessage>{{ form.errors.password }}</FormMessage>
                    </FormItem>
                </FormField>

                <FormField name="password_confirmation">
                    <FormItem class="flex flex-col gap-2">
                        <FormLabel>Potwierdź nowe hasło</FormLabel>
                        <FormControl>
                            <div class="relative">
                                <KeyRound class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                <Input 
                                    type="password" 
                                    v-model="form.password_confirmation" 
                                    class="pl-10"
                                    required 
                                    autocomplete="new-password"
                                />
                            </div>
                        </FormControl>
                        <FormMessage>{{ form.errors.password_confirmation }}</FormMessage>
                    </FormItem>
                </FormField>

                <Button type="submit" class="w-full h-12 text-lg gap-2" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="size-5 animate-spin" />
                    {{ form.processing ? 'Resetowanie...' : 'Zresetuj hasło' }}
                </Button>
            </form>
        </div>
    </GuestLayout>
</template>
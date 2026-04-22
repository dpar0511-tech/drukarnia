<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Mail, ArrowLeft, Loader2 } from 'lucide-vue-next'
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/Components/ui/form'

defineProps({
    status: String,
})

const form = useForm({
    email: '',
})

const submit = () => {
    form.post('/forgot-password')
}
</script>

<template>
    <GuestLayout>
        <Head title="Zapomniałeś hasła?" />

        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-3xl font-bold tracking-tight text-foreground">Zapomniałeś hasła?</h1>
                <p class="text-lg text-muted-foreground">
                    Podaj swój adres email, a wyślemy Ci link do zresetowania hasła.
                </p>
            </div>

            <div v-if="status" class="p-4 bg-green-500/10 border border-green-500/20 rounded-lg text-green-600 text-sm font-medium">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-5">
                <FormField name="email">
                    <FormItem class="flex flex-col gap-2">
                        <FormLabel>Email</FormLabel>
                        <FormControl>
                            <div class="relative">
                                <Mail class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                <Input 
                                    type="email" 
                                    v-model="form.email" 
                                    placeholder="twoj@email.pl" 
                                    class="pl-10"
                                    required 
                                    autofocus 
                                    autocomplete="username"
                                />
                            </div>
                        </FormControl>
                        <FormMessage>{{ form.errors.email }}</FormMessage>
                    </FormItem>
                </FormField>

                <Button type="submit" class="w-full h-12 text-lg gap-2" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="size-5 animate-spin" />
                    {{ form.processing ? 'Wysyłanie...' : 'Wyślij link do resetu' }}
                </Button>
            </form>

            <div class="pt-4 border-t flex flex-col gap-4">
                <Link 
                    :href="route('login')" 
                    class="text-sm text-muted-foreground hover:text-primary flex items-center gap-2 transition-colors"
                >
                    <ArrowLeft class="size-4" />
                    Wróć do logowania
                </Link>
            </div>
        </div>
    </GuestLayout>
</template>
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'E-Mail bestaetigen',
        description:
            'Bitte bestaetige deine E-Mail-Adresse ueber den Link, den wir dir gerade gesendet haben.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="E-Mail-Bestaetigung" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        Ein neuer Bestaetigungslink wurde an die E-Mail-Adresse gesendet, die du
        bei der Registrierung angegeben hast.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Bestaetigungs-E-Mail erneut senden
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Abmelden
        </TextLink>
    </Form>
</template>

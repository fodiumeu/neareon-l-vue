<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { ContactStatus } from '@/types';

defineProps<{
    canFollow: boolean;
    canSendContactRequest: boolean;
    contactRequestId?: number | null;
    contactRequestUnavailableReason?: 'disabled' | 'follow_required' | null;
    isFollowing: boolean;
    status: ContactStatus;
    stayOnPage?: boolean;
    userId: number;
    username: string;
}>();
</script>

<template>
    <div
        v-if="status === 'incoming_request' && contactRequestId"
        class="flex flex-col gap-2 sm:flex-row"
    >
        <Form
            :action="`/contact-requests/${contactRequestId}/accept`"
            method="patch"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
            class="flex-1"
        >
            <Button type="submit" :disabled="processing" class="w-full">
                <Spinner v-if="processing" />
                Annehmen
            </Button>
        </Form>

        <Form
            :action="`/contact-requests/${contactRequestId}/decline`"
            method="patch"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
            class="flex-1"
        >
            <Button
                type="submit"
                variant="secondary"
                :disabled="processing"
                class="w-full"
            >
                <Spinner v-if="processing" />
                Ablehnen
            </Button>
        </Form>
    </div>

    <div v-else class="flex flex-col gap-2">
        <Form
            v-if="status === 'connected'"
            :action="`/contacts/${userId}/messages`"
            method="post"
            v-slot="{ processing }"
        >
            <Button type="submit" :disabled="processing" class="w-full">
                <Spinner v-if="processing" />
                Nachricht senden
            </Button>
        </Form>

        <Form
            v-if="isFollowing || canFollow"
            :action="`/u/${username}/follow`"
            :method="isFollowing ? 'delete' : 'post'"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
        >
            <input
                v-if="stayOnPage"
                type="hidden"
                name="context"
                value="discover"
            />
            <Button
                type="submit"
                :variant="isFollowing ? 'secondary' : 'default'"
                :disabled="processing"
                class="w-full"
            >
                <Spinner v-if="processing" />
                {{ isFollowing ? 'Entfolgen' : 'Folgen' }}
            </Button>
        </Form>

        <Button
            v-else
            type="button"
            variant="secondary"
            disabled
            class="w-full"
        >
            Folgen deaktiviert
        </Button>

        <Form
            v-if="status === 'none' && canSendContactRequest"
            action="/contact-requests"
            method="post"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
        >
            <input type="hidden" name="receiver_id" :value="userId" />
            <Button type="submit" :disabled="processing" class="w-full">
                <Spinner v-if="processing" />
                Kontaktanfrage senden
            </Button>
        </Form>

        <Button
            v-else-if="
                status === 'none' &&
                contactRequestUnavailableReason === 'follow_required'
            "
            type="button"
            variant="secondary"
            disabled
            class="w-full"
        >
            Erst folgen
        </Button>

        <Button
            v-else-if="
                status === 'none' &&
                contactRequestUnavailableReason === 'disabled'
            "
            type="button"
            variant="secondary"
            disabled
            class="w-full"
        >
            Kontaktanfragen deaktiviert
        </Button>

        <Button
            v-else-if="status === 'outgoing_request'"
            type="button"
            variant="secondary"
            disabled
            class="w-full"
        >
            Kontaktanfrage gesendet
        </Button>
    </div>
</template>

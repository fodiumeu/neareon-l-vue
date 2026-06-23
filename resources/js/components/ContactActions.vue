<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    acceptContactRequestAction,
    contactMessageAction,
    followAction,
    rejectContactRequestAction,
    relationshipActionUnavailableText,
    sendContactRequestAction,
} from '@/lib/relationshipActions';
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
            :action="acceptContactRequestAction(contactRequestId).action"
            :method="acceptContactRequestAction(contactRequestId).method"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
            class="flex-1"
        >
            <Button type="submit" :disabled="processing" class="w-full">
                <Spinner v-if="processing" />
                {{ acceptContactRequestAction(contactRequestId).label }}
            </Button>
        </Form>

        <Form
            :action="rejectContactRequestAction(contactRequestId).action"
            :method="rejectContactRequestAction(contactRequestId).method"
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
                {{ rejectContactRequestAction(contactRequestId).label }}
            </Button>
        </Form>
    </div>

    <div v-else class="flex flex-col gap-2">
        <Form
            v-if="status === 'connected'"
            :action="contactMessageAction(userId).action"
            :method="contactMessageAction(userId).method"
            v-slot="{ processing }"
        >
            <Button type="submit" :disabled="processing" class="w-full">
                <Spinner v-if="processing" />
                {{ contactMessageAction(userId).label }}
            </Button>
        </Form>

        <Form
            v-if="isFollowing || canFollow"
            :action="followAction(username, isFollowing).action"
            :method="followAction(username, isFollowing).method"
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
                {{ followAction(username, isFollowing).label }}
            </Button>
        </Form>

        <Button
            v-else
            type="button"
            variant="secondary"
            disabled
            class="w-full"
        >
            {{ relationshipActionUnavailableText.followDisabled }}
        </Button>

        <Form
            v-if="status === 'none' && canSendContactRequest"
            :action="sendContactRequestAction().action"
            :method="sendContactRequestAction().method"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
        >
            <input type="hidden" name="receiver_id" :value="userId" />
            <Button type="submit" :disabled="processing" class="w-full">
                <Spinner v-if="processing" />
                {{ sendContactRequestAction().label }}
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
            {{ relationshipActionUnavailableText.contactRequestFollowRequired }}
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
            {{ relationshipActionUnavailableText.contactRequestDisabled }}
        </Button>

        <Button
            v-else-if="status === 'outgoing_request'"
            type="button"
            variant="secondary"
            disabled
            class="w-full"
        >
            {{ relationshipActionUnavailableText.contactRequestSent }}
        </Button>
    </div>
</template>

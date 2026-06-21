<script setup lang="ts">
import { X } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { ref } from 'vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

defineProps<{
    alt: string;
    fallback: string;
    photoUrl?: string | null;
    class?: HTMLAttributes['class'];
    fallbackClass?: HTMLAttributes['class'];
}>();

const isOpen = ref(false);
</script>

<template>
    <Dialog v-if="photoUrl" v-model:open="isOpen">
        <DialogTrigger as-child>
            <button
                type="button"
                class="shrink-0 rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-ring/60"
                :aria-label="`Profilbild von ${alt} vergrößern`"
                aria-haspopup="dialog"
            >
                <ProfileAvatar
                    :photo-url="photoUrl"
                    :alt="alt"
                    :fallback="fallback"
                    :class="$props.class"
                    :fallback-class="fallbackClass"
                />
            </button>
        </DialogTrigger>

        <DialogContent
            :show-close-button="false"
            class="h-[100dvh] max-h-[100dvh] w-screen max-w-none place-items-center rounded-none border-0 bg-transparent p-4 shadow-none sm:h-auto sm:max-h-[calc(100dvh-2rem)] sm:w-[min(90vw,64rem)] sm:max-w-5xl sm:rounded-xl sm:border sm:bg-black/95 sm:p-6 sm:shadow-lg"
            data-test="profile-photo-lightbox-overlay"
            @click.self="isOpen = false"
        >
            <DialogTitle class="sr-only">
                Profilbild von {{ alt }}
            </DialogTitle>
            <DialogDescription class="sr-only">
                Vergrößerte Ansicht des Profilbilds.
            </DialogDescription>

            <div
                class="flex max-h-full max-w-full items-center justify-center"
                data-test="profile-photo-lightbox-image-container"
                @click.stop
            >
                <img
                    :src="photoUrl"
                    :alt="`Vergrößertes Profilbild von ${alt}`"
                    class="max-h-[calc(100dvh-5rem)] max-w-full object-contain sm:max-h-[calc(100dvh-6rem)]"
                    data-test="profile-photo-lightbox-image"
                />
            </div>

            <DialogClose
                class="absolute top-4 right-4 inline-flex size-10 items-center justify-center rounded-full border border-white/20 bg-black/70 text-white shadow-lg transition-colors hover:bg-black focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                aria-label="Profilbild schließen"
            >
                <X class="size-5" aria-hidden="true" />
            </DialogClose>
        </DialogContent>
    </Dialog>

    <ProfileAvatar
        v-else
        :photo-url="photoUrl"
        :alt="alt"
        :fallback="fallback"
        :class="$props.class"
        :fallback-class="fallbackClass"
    />
</template>

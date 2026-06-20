<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

const props = defineProps<{
    alt: string;
    fallback: string;
    photoUrl?: string | null;
    class?: HTMLAttributes['class'];
    fallbackClass?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Avatar :class="cn('border border-primary/25', props.class)">
        <AvatarImage
            v-if="props.photoUrl"
            :src="props.photoUrl"
            :alt="props.alt"
            class="object-cover"
        />
        <AvatarFallback
            :class="
                cn(
                    'bg-primary/15 font-semibold text-primary',
                    props.fallbackClass,
                )
            "
        >
            {{ props.fallback }}
        </AvatarFallback>
    </Avatar>
</template>

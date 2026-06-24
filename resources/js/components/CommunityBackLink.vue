<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
</script>

<template>
    <Button
        as-child
        variant="ghost"
        size="sm"
        class="w-fit px-2 text-muted-foreground hover:text-foreground focus-visible:border-primary/60 focus-visible:ring-primary/45"
    >
        <Link href="/community">
            <ArrowLeft aria-hidden="true" />
            Zurück zur Community
        </Link>
    </Button>
</template>

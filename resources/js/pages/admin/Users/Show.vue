<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import AdminNavigation from '@/components/AdminNavigation.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type { UserRole } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    email_verified_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    user: AdminUser;
}>();

const page = usePage<{
    auth: {
        user: {
            id: number;
        } | null;
    };
}>();

const availableRoles: UserRole[] = ['member', 'moderator', 'admin', 'owner'];
const roleLabels: Record<UserRole, string> = {
    member: 'Mitglied',
    moderator: 'Moderator',
    admin: 'Administrator',
    owner: 'Inhaber',
};

const formatDateTime = (value: string | null) => {
    if (!value) {
        return 'Nicht gesetzt';
    }

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
            {
                title: 'Benutzer',
                href: '/admin',
            },
        ],
    },
});
</script>

<template>
    <Head :title="props.user.name" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            :title="props.user.name"
            description="Kontodetails und Rollenverwaltung im Administrationsbereich"
        />

        <AdminNavigation />

        <PageSection>
            <Card>
                <CardHeader>
                    <CardTitle>Benutzerdetails</CardTitle>
                    <CardDescription>
                        Grundlegende Informationen zu diesem Benutzerkonto.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Name
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ props.user.name }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            E-Mail-Adresse
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ props.user.email }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Rolle
                        </p>
                        <Badge variant="secondary" class="mt-2">
                            {{ roleLabels[props.user.role] }}
                        </Badge>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            E-Mail bestätigt am
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ formatDateTime(props.user.email_verified_at) }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Erstellt am
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ formatDateTime(props.user.created_at) }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Aktualisiert am
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ formatDateTime(props.user.updated_at) }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-if="page.props.auth.user?.id !== props.user.id">
            <Card>
                <CardHeader>
                    <CardTitle>Rollenverwaltung</CardTitle>
                    <CardDescription>
                        Weise diesem Benutzer eine passende Plattformrolle zu.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        :action="`/admin/users/${props.user.id}/role`"
                        method="patch"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="role">Rolle</Label>
                            <select
                                id="role"
                                name="role"
                                class="flex h-10 w-full rounded-md border border-input bg-background/80 px-3 py-2 text-sm text-foreground shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border/90 dark:bg-input/60"
                                :default-value="props.user.role"
                            >
                                <option
                                    v-for="role in availableRoles"
                                    :key="role"
                                    :value="role"
                                    class="bg-popover text-popover-foreground"
                                >
                                    {{ roleLabels[role] }}
                                </option>
                            </select>
                            <InputError :message="errors.role" />
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="processing">
                                Rolle speichern
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>

<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
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
import InputError from '@/components/InputError.vue';
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

const availableRoles: UserRole[] = ['member', 'admin'];

const formatDateTime = (value: string | null) => {
    if (!value) {
        return 'Not set';
    }

    return new Intl.DateTimeFormat('en', {
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
                title: 'Users',
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
            description="Read-only user details in the administration area"
        />

        <PageSection>
            <Card>
                <CardHeader>
                    <CardTitle>User details</CardTitle>
                    <CardDescription>
                        Basic account information for this user account.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-lg border border-border px-4 py-3">
                        <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Name
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ props.user.name }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Email
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ props.user.email }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Role
                        </p>
                        <Badge variant="secondary" class="mt-2 capitalize">
                            {{ props.user.role }}
                        </Badge>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Email verified at
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ formatDateTime(props.user.email_verified_at) }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Created at
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ formatDateTime(props.user.created_at) }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Updated at
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
                    <CardTitle>Role management</CardTitle>
                    <CardDescription>
                        Update this user's role between member and admin.
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
                            <Label for="role">Role</Label>
                            <select
                                id="role"
                                name="role"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                :default-value="props.user.role"
                            >
                                <option
                                    v-for="role in availableRoles"
                                    :key="role"
                                    :value="role"
                                >
                                    {{ role }}
                                </option>
                            </select>
                            <InputError :message="errors.role" />
                        </div>

                        <div class="flex items-center gap-4">
                            <Button :disabled="processing">
                                Update role
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>

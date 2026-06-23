type RelationshipActionMethod = 'delete' | 'patch' | 'post';

type RelationshipAction = {
    action: string;
    label: string;
    method: RelationshipActionMethod;
};

export const relationshipActionUnavailableText = {
    contactRequestDisabled: 'Dieses Profil nimmt keine Kontaktanfragen an',
    contactRequestFollowRequired: 'Folge diesem Profil zuerst',
    contactRequestSent: 'Kontaktanfrage gesendet',
    followDisabled: 'Dieses Profil erlaubt keine neuen Follower',
} as const;

export const profileUrl = (username: string) => `/u/${username}`;

export const contactMessageAction = (userId: number): RelationshipAction => ({
    action: `/contacts/${userId}/messages`,
    label: 'Nachricht senden',
    method: 'post',
});

export const followAction = (
    username: string,
    isFollowing: boolean,
): RelationshipAction => ({
    action: `${profileUrl(username)}/follow`,
    label: isFollowing ? 'Entfolgen' : 'Folgen',
    method: isFollowing ? 'delete' : 'post',
});

export const sendContactRequestAction = (): RelationshipAction => ({
    action: '/contact-requests',
    label: 'Kontaktanfrage senden',
    method: 'post',
});

export const acceptContactRequestAction = (
    contactRequestId: number,
): RelationshipAction => ({
    action: `/contact-requests/${contactRequestId}/accept`,
    label: 'Annehmen',
    method: 'patch',
});

export const rejectContactRequestAction = (
    contactRequestId: number,
): RelationshipAction => ({
    action: `/contact-requests/${contactRequestId}/decline`,
    label: 'Ablehnen',
    method: 'patch',
});

export const cancelContactRequestAction = (): null => null;

export const removeContactAction = (userId: number): RelationshipAction => ({
    action: `/contacts/${userId}`,
    label: 'Verbindung entfernen',
    method: 'delete',
});

export const blockUserAction = (username: string): RelationshipAction => ({
    action: `${profileUrl(username)}/block`,
    label: 'Blockieren',
    method: 'post',
});

export const unblockUserAction = (username: string): RelationshipAction => ({
    action: `${profileUrl(username)}/block`,
    label: 'Blockierung aufheben',
    method: 'delete',
});

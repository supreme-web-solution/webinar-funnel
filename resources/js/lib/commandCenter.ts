export type CommandCenterMessage = {
    id: string;
    role: string;
    content: string;
    created_at: string | null;
};

export type CommandCenterApproval = {
    id: number;
    launch_label: string;
    tool_name: string;
    summary: string;
    status: string;
};

export type CommandCenterMessagesMeta = {
    has_older: boolean;
    oldest_id: string | null;
};

export type CommandCenterState = {
    employee: { name: string; title: string; avatar_url: string };
    conversation_id: string | null;
    processing: boolean;
    progress: string | null;
    messages: CommandCenterMessage[];
    messages_meta?: CommandCenterMessagesMeta;
    approvals: CommandCenterApproval[];
    activity: Array<{
        id: number;
        tool_name: string;
        permission: string;
        status: string;
        result: string | null;
        created_at: string | null;
    }>;
    attention: Array<{ type: string; title: string; detail: string; href?: string }>;
    status: {
        campaigns?: { total?: number; published?: number; draft?: number };
        funnels?: number;
        leads?: number;
        tracked_links?: number;
        social_accounts?: number;
        promotion_posts?: number;
    };
    settings: {
        killed: boolean;
        autonomy: string;
        execute_allowlist: string[];
        whatsapp_phone: string | null;
        whatsapp_linked: boolean;
    };
    suggestions: string[];
    execute_tools: Array<{ name: string; label: string }>;
    whatsapp: {
        configured: boolean;
        phone: string | null;
        pairing: {
            code: string;
            expires_at: string;
            phone: string | null;
            wa_link: string;
            qr_url: string;
        } | null;
        linked_phone: string | null;
    };
};

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export async function commandCenterFetch(url: string, init: RequestInit = {}): Promise<Response> {
    return fetch(url, {
        ...init,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(init.headers ?? {}),
        },
    });
}

/** Avoid wiping chat history when a partial state payload omits or clears messages mid-turn. */
export function mergeCommandCenterState(
    previous: CommandCenterState,
    incoming: CommandCenterState,
): CommandCenterState {
    const conversationChanged =
        incoming.conversation_id != null &&
        previous.conversation_id != null &&
        incoming.conversation_id !== previous.conversation_id;

    const incomingMessages = incoming.messages ?? [];
    const previousMessages = previous.messages ?? [];

    let messages = incomingMessages;
    if (!conversationChanged && incomingMessages.length === 0 && previousMessages.length > 0) {
        messages = previousMessages;
    }

    return {
        ...previous,
        ...incoming,
        messages,
    };
}

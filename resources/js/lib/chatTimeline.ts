import type { CommandCenterMessage } from '@/lib/commandCenter';

export type ChatTimelineItem =
    | { kind: 'date'; key: string; label: string }
    | { kind: 'message'; key: string; message: CommandCenterMessage };

export function dayKey(iso: string | null): string {
    if (!iso) return 'unknown';
    const d = new Date(iso);
    return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}

export function dateSeparatorLabel(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const startMsg = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const diffDays = Math.round((startToday.getTime() - startMsg.getTime()) / 86_400_000);

    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) {
        return d.toLocaleDateString(undefined, { weekday: 'long' });
    }

    return d.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
    });
}

export function buildChatTimeline(messages: CommandCenterMessage[]): ChatTimelineItem[] {
    const items: ChatTimelineItem[] = [];
    let lastDay: string | null = null;

    for (const message of messages) {
        const dk = dayKey(message.created_at);
        if (dk !== lastDay) {
            lastDay = dk;
            items.push({
                kind: 'date',
                key: `date-${dk}`,
                label: dateSeparatorLabel(message.created_at),
            });
        }
        items.push({ kind: 'message', key: String(message.id), message });
    }

    return items;
}

export function mergeMessagesById(
    existing: CommandCenterMessage[],
    incoming: CommandCenterMessage[],
): CommandCenterMessage[] {
    const map = new Map<string, CommandCenterMessage>();
    for (const row of existing) {
        map.set(String(row.id), row);
    }
    for (const row of incoming) {
        map.set(String(row.id), row);
    }

    return [...map.values()].sort((a, b) => {
        const ta = a.created_at ? new Date(a.created_at).getTime() : 0;
        const tb = b.created_at ? new Date(b.created_at).getTime() : 0;
        if (ta !== tb) return ta - tb;
        return String(a.id).localeCompare(String(b.id));
    });
}

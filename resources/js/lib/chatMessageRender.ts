const TOKEN_OPEN = '\uE000';
const TOKEN_CLOSE = '\uE001';

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function sanitizeHref(url: string): string {
    const trimmed = url.replace(/[)\].,;:!?]+$/g, '').trim();
    if (/^https?:\/\//i.test(trimmed) || trimmed.startsWith('/')) {
        return trimmed.replace(/"/g, '%22');
    }

    return '#';
}

function linkAnchor(label: string, href: string): string {
    const safeHref = sanitizeHref(href);
    const isExternal = /^https?:\/\//i.test(safeHref);

    return `<a href="${escapeHtml(safeHref)}" class="chat-message-link"${
        isExternal ? ' target="_blank" rel="noopener noreferrer"' : ''
    }>${escapeHtml(label)}</a>`;
}

/** Legacy messages: replace trailing tool JSON with a short summary. */
export function preprocessChatText(text: string): string {
    const match = text.match(/\n(\{[\s\S]*\})\s*$/);
    if (!match?.[1]) {
        return text;
    }

    try {
        const data = JSON.parse(match[1]) as Record<string, unknown>;
        if (data.created === true && data.id != null) {
            const name = String(data.name ?? 'Campaign');
            const id = data.id;
            const edit = String(data.edit_url ?? `/campaigns/${id}/edit`);
            const queued =
                data.queued === true ? '\nAI generation is running in the background.' : '';
            const friendly = `**Campaign created:** ${name} (#${id})${queued}\n[Open campaign editor](${edit})`;

            return text.slice(0, match.index) + '\n\n' + friendly;
        }
    } catch {
        /* keep original */
    }

    return text;
}

export function renderChatContent(raw: string): string {
    const tokens: string[] = [];
    const stash = (html: string): string => {
        const id = tokens.length;
        tokens.push(html);

        return `${TOKEN_OPEN}${id}${TOKEN_CLOSE}`;
    };

    let work = preprocessChatText(raw);

    // Markdown links: [label](https://...) or [label](/app/path)
    work = work.replace(/\[([^\]\n]+)\]\(([^)\s]+)\)/g, (full, label: string, url: string) => {
        const href = url.trim();
        if (!/^https?:\/\//i.test(href) && !href.startsWith('/')) {
            return full;
        }

        return stash(linkAnchor(label, href));
    });

    // Bare URLs (including parenthesized)
    work = work.replace(
        /(\()?(https?:\/\/[^\s<>"']+?)(?=[)\s<>"']|[.,;:!?]+(?:\s|$)|$)/gi,
        (full, openParen: string | undefined, rawUrl: string) => {
            let url = rawUrl.replace(/[),.;:!?]+$/g, '');
            const trailing = rawUrl.slice(url.length);
            if (!/^https?:\/\//i.test(url)) {
                return full;
            }
            const anchor = stash(linkAnchor(url, url));
            if (openParen) {
                return `(${anchor}${escapeHtml(trailing)})`;
            }

            return `${anchor}${escapeHtml(trailing)}`;
        },
    );

    let html = escapeHtml(work);

    html = html.replace(
        new RegExp(`${TOKEN_OPEN}(\\d+)${TOKEN_CLOSE}`, 'g'),
        (_m, index: string) => tokens[Number(index)] ?? '',
    );

    html = html
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<code class="rounded bg-slate-100 px-1 py-0.5 text-[0.8em]">$1</code>')
        .replace(/\n/g, '<br>');

    return html;
}

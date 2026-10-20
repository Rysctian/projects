function humanize(value: string): string {
    return value
        .replace(/[._-]+/g, ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}

function display(value: unknown): string | null {
    if (value === null || value === undefined || value === '') return 'Not set';
    if (Array.isArray(value))
        return value.map(display).filter(Boolean).join('; ') || 'None';
    if (typeof value === 'string') return value;
    if (typeof value === 'number' || typeof value === 'boolean')
        return value.toString();
    return null;
}

export function activitySummary(
    type: string,
    payload: object,
): { title: string; details: string[] } {
    const data = payload as Record<string, unknown>;
    const details: string[] = [];
    if (typeof data.from === 'string' && typeof data.to === 'string')
        details.push(`Stage: ${data.from} → ${data.to}`);
    for (const key of [
        'reason',
        'note',
        'completion_notes',
        'documentation_impact',
        'documentation_impact_note',
        'from_stage',
        'to_stage',
        'kind',
        'result',
        'filename',
    ]) {
        if (data[key] === undefined || data[key] === null || data[key] === '')
            continue;
        const value = display(data[key]);
        if (value !== null) details.push(`${humanize(key)}: ${value}`);
    }
    if (
        data.changes &&
        typeof data.changes === 'object' &&
        !Array.isArray(data.changes)
    ) {
        for (const [field, change] of Object.entries(data.changes)) {
            if (
                !change ||
                typeof change !== 'object' ||
                !('from' in change) ||
                !('to' in change)
            )
                continue;
            const before = display(change.from);
            const after = display(change.to);
            if (before !== null && after !== null)
                details.push(`${humanize(field)}: ${before} → ${after}`);
        }
    }
    if (
        data.before &&
        data.after &&
        typeof data.before === 'object' &&
        typeof data.after === 'object'
    ) {
        const before = data.before as Record<string, unknown>;
        const after = data.after as Record<string, unknown>;
        for (const field of Object.keys(after)) {
            if (JSON.stringify(before[field]) === JSON.stringify(after[field]))
                continue;
            const previous = display(before[field]);
            const next = display(after[field]);
            if (previous !== null && next !== null)
                details.push(`${humanize(field)}: ${previous} → ${next}`);
        }
    }
    return { title: humanize(type), details };
}

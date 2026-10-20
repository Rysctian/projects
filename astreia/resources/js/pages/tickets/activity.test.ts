import { describe, expect, it } from 'vitest';
import { activitySummary } from './activity';

describe('activitySummary', () => {
    it('keeps action reasons and completion decisions visible without exposing nested objects', () => {
        const summary = activitySummary('ticket.closed', {
            reason: 'Client confirmed',
            completion_notes: 'Deployed and checked',
            documentation_impact: 'required',
            internal: { id: 42 },
        });
        expect(summary.title).toBe('Ticket closed');
        expect(summary.details).toContain('Reason: Client confirmed');
        expect(summary.details).toContain(
            'Completion notes: Deployed and checked',
        );
        expect(summary.details).toContain('Documentation impact: required');
        expect(summary.details.join(' ')).not.toMatch(
            /\[object Object\]|internal|42/,
        );
    });

    it('describes field changes while preserving zero and unassigned values', () => {
        const summary = activitySummary('ticket.updated', {
            changes: {
                estimate_minutes: { from: null, to: 0 },
                assigned_to: { from: 7, to: null },
                acceptance_criteria: { from: [], to: ['Works offline'] },
            },
        });
        expect(summary.details).toContain('Estimate minutes: Not set → 0');
        expect(summary.details).toContain('Assigned to: 7 → Not set');
        expect(summary.details).toContain(
            'Acceptance criteria: None → Works offline',
        );
    });

    it('safely summarizes unknown events without rendering arbitrary payload data', () => {
        expect(
            activitySummary('workflow.stage_revisited', {
                nested: { unexpected: 'data' },
            }),
        ).toEqual({ title: 'Workflow stage revisited', details: [] });
    });

    it('formats the backend before/after payload and stage transition names', () => {
        expect(
            activitySummary('updated', {
                before: { title: 'Old title', estimate_minutes: 0 },
                after: { title: 'New title', estimate_minutes: 0 },
            }).details,
        ).toEqual(['Title: Old title → New title']);
        expect(
            activitySummary('transitioned', {
                from: 'Development 2',
                to: 'Staging',
                reason: 'Ready for checks',
            }).details,
        ).toContain('Stage: Development 2 → Staging');
    });
});

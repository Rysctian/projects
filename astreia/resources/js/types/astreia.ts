export type Person = { id: number; name: string };
export type Project = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    status: string;
    current_workflow_id?: number | null;
};
export type Stage = { id: number; name: string; type: string };
export type Capabilities = Record<string, boolean>;
export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};
export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    total?: number;
};
export type TicketSummary = {
    id: number;
    key: string;
    title: string | null;
    category: string;
    priority: string;
    stage: Stage;
    assigned_to: number | null;
    assignee: Person | null;
    due_date: string | null;
    is_intake_stub: boolean;
    is_blocked: boolean;
    is_cancelled: boolean;
    lock_version: number;
};
export type Module = {
    id: number;
    name: string;
    code?: string;
    status?: string;
};
export type Label = { id: number; name: string };
export type Role = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    permissions: { id: number; key: string }[];
};
export type Member = {
    id: number;
    is_active: boolean;
    user: Person & { email: string };
    roles: { id: number; name: string }[];
    overrides: {
        permission_id: number;
        effect: 'allow' | 'deny';
        permission: { id: number; key: string };
    }[];
};
export type WorkflowStage = Stage & {
    key: string;
    sort_order: number;
    is_initial: boolean;
    is_active: boolean;
};
export type Workflow = {
    id: number;
    version_number: number;
    state: string;
    stages: WorkflowStage[];
    transitions: {
        id: number;
        from_stage_id: number;
        to_stage_id: number;
        required_permission_id: number | null;
        requires_reason: boolean;
        requires_signoff: boolean;
        required_permission: { key: string } | null;
    }[];
};
export type Ticket = TicketSummary & {
    description: string | null;
    workflow: { id: number; version_number: number };
    revision_number: number;
    stage_visit: number;
    module_id: number | null;
    estimate_minutes: number | null;
    acceptance_criteria: string[];
    completion_notes: string | null;
    documentation_impact: 'required' | 'not_required' | null;
    documentation_impact_note: string | null;
    reporter: Person;
    module: Module | null;
    labels: Label[];
};
export type TicketTransition = {
    id: number;
    to_stage: Stage;
    requires_reason: boolean;
    requires_signoff: boolean;
};
export type Activity = {
    id: number;
    activity_type: string;
    payload: object;
    created_at: string;
    actor: { name: string } | null;
};
export type Signoff = {
    id: number;
    kind: string;
    result: string;
    note: string | null;
    verified_revision: number;
    stage_visit: number;
    actor: { name: string };
};

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, ShieldCheck, Zap } from '@lucide/vue';
import FormSurface from '@/components/astreia/FormSurface.vue';
import Field from '@/components/astreia/Field.vue';
import FormErrors from '@/components/astreia/FormErrors.vue';
import { Button } from '@/components/ui/button';
import type { Capabilities, Ticket, TicketTransition } from '@/types/astreia';
const props = defineProps<{
    base: string;
    ticket: Ticket;
    transitions: TicketTransition[];
    capabilities: Capabilities;
}>();
const moving = ref(false);
const verifying = ref(false);
const acting = ref(false);
const transition = useForm({
    lock_version: props.ticket.lock_version,
    transition_id: '' as number | '',
    reason: '',
    completion_notes: props.ticket.completion_notes ?? '',
    documentation_impact: props.ticket.documentation_impact,
    documentation_impact_note: props.ticket.documentation_impact_note ?? '',
});
const signoff = useForm({
    lock_version: props.ticket.lock_version,
    kind: 'qa' as 'qa' | 'review' | 'staging' | 'resolution',
    result: 'pass' as 'pass' | 'fail',
    note: '',
});
const action = useForm({
    lock_version: props.ticket.lock_version,
    action: '' as '' | 'block' | 'resume' | 'cancel' | 'reopen',
    reason: '',
});
const destinations = computed(() =>
    !props.capabilities['tickets.transition'] ||
    props.ticket.is_cancelled ||
    props.ticket.is_blocked ||
    props.ticket.stage.type === 'closed'
        ? []
        : props.transitions.filter(
              (item) =>
                  item.to_stage.type !== 'closed' ||
                  props.capabilities['tickets.close'],
          ),
);
const selected = computed(() =>
    destinations.value.find((item) => item.id === transition.transition_id),
);
const closing = computed(() => selected.value?.to_stage.type === 'closed');
const docDecision = computed(() =>
    ['new_feature', 'enhancement'].includes(props.ticket.category),
);
const actions = computed(() => {
    const values: {
        value: 'block' | 'resume' | 'cancel' | 'reopen';
        label: string;
    }[] = [];
    if (props.ticket.is_cancelled || props.ticket.stage.type === 'closed') {
        if (
            props.capabilities['tickets.transition'] &&
            props.capabilities['tickets.close']
        )
            values.push({ value: 'reopen', label: 'Reopen ticket' });
        return values;
    }
    if (props.capabilities['tickets.transition'])
        values.push(
            props.ticket.is_blocked
                ? { value: 'resume', label: 'Resume work' }
                : { value: 'block', label: 'Block ticket' },
        );
    if (
        props.capabilities['tickets.transition'] &&
        props.capabilities['tickets.close'] &&
        !props.ticket.is_intake_stub
    )
        values.push({ value: 'cancel', label: 'Cancel ticket' });
    return values;
});
function move() {
    transition.lock_version = props.ticket.lock_version;
    transition.post(`${props.base}/transitions`, {
        preserveScroll: true,
        onSuccess: () => {
            transition.reset('transition_id', 'reason');
            moving.value = false;
        },
    });
}
function verify() {
    signoff.lock_version = props.ticket.lock_version;
    signoff.post(`${props.base}/signoffs`, {
        preserveScroll: true,
        onSuccess: () => {
            signoff.reset('note');
            verifying.value = false;
        },
    });
}
function act() {
    action.lock_version = props.ticket.lock_version;
    action.post(`${props.base}/actions`, {
        preserveScroll: true,
        onSuccess: () => {
            action.reset('action', 'reason');
            acting.value = false;
        },
    });
}
</script>
<template>
    <section class="panel space-y-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
            <ArrowRight class="size-4 text-primary" />Workflow
        </h2>
        <p class="muted">Move this work to its next checkpoint.</p>
        <FormSurface
            v-if="destinations.length"
            v-model:open="moving"
            title="Move ticket"
            description="Choose the next stage. Workflow checks and sign-offs still apply."
            trigger="Move ticket"
            :busy="transition.processing"
        >
            <form
                v-if="destinations.length"
                class="space-y-4"
                @submit.prevent="move"
            >
                <FormErrors
                    :errors="transition.errors"
                    :reload-url="base"
                /><Field label="Allowed destination"
                    ><select
                        v-model="transition.transition_id"
                        required
                        class="control"
                    >
                        <option value="" disabled>Select next stage</option>
                        <option
                            v-for="item in destinations"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.to_stage.name }}
                        </option>
                    </select></Field
                >
                <p
                    v-if="selected?.requires_signoff"
                    class="rounded-md bg-muted p-3 text-xs"
                >
                    A valid manual sign-off is required before this transition.
                </p>
                <Field
                    :label="
                        selected?.requires_reason
                            ? 'Reason (required)'
                            : 'Transition reason / notes'
                    "
                >
                    <textarea
                        v-model="transition.reason"
                        class="control"
                        :required="selected?.requires_reason"
                    />
                </Field>
                <template v-if="closing"
                    ><Field label="Completion notes">
                        <textarea
                            v-model="transition.completion_notes"
                            class="control"
                            required
                            placeholder="What was completed and how was it checked?"
                        /></Field
                    ><template v-if="docDecision"
                        ><Field label="Documentation impact"
                            ><select
                                v-model="transition.documentation_impact"
                                class="control"
                                required
                            >
                                <option :value="null" disabled>
                                    Select a decision
                                </option>
                                <option value="required">
                                    Required · pending future documentation
                                </option>
                                <option value="not_required">
                                    Not required
                                </option>
                            </select></Field
                        ><Field
                            :label="
                                transition.documentation_impact ===
                                'not_required'
                                    ? 'Why is documentation not required?'
                                    : 'Documentation impact note'
                            "
                        >
                            <textarea
                                v-model="transition.documentation_impact_note"
                                :required="
                                    transition.documentation_impact ===
                                    'not_required'
                                "
                                class="control"
                            />
                        </Field>
                        <p class="muted">
                            A required impact records pending work. It does not
                            publish documentation.
                        </p></template
                    ></template
                >
                <Button
                    class="w-full"
                    :disabled="
                        transition.processing || !transition.transition_id
                    "
                    >{{
                        transition.processing
                            ? 'Moving…'
                            : closing
                              ? 'Close ticket'
                              : 'Move to selected stage'
                    }}</Button
                >
            </form>
        </FormSurface>
        <p v-else class="muted">
            No transitions are available for your account in this stage.
        </p>
    </section>
    <section
        v-if="
            capabilities['tickets.verify'] &&
            ['review', 'verification', 'pre_close'].includes(
                ticket.stage.type,
            ) &&
            !ticket.is_blocked &&
            !ticket.is_cancelled
        "
        class="panel space-y-4"
    >
        <h2 class="flex items-center gap-2 text-sm font-semibold">
            <ShieldCheck class="size-4 text-emerald-500" />Verification
        </h2>
        <p class="muted">
            Applies to requirements revision {{ ticket.revision_number }}.
            Material requirement changes invalidate earlier approvals.
        </p>
        <FormSurface
            v-model:open="verifying"
            title="Record verification"
            description="Record the checks performed against the current requirements."
            trigger="Record verification"
            :busy="signoff.processing"
        >
            <form class="space-y-4" @submit.prevent="verify">
                <FormErrors :errors="signoff.errors" :reload-url="base" />
                <div class="grid grid-cols-2 gap-3">
                    <Field label="Kind"
                        ><select v-model="signoff.kind" class="control">
                            <option value="qa">QA</option>
                            <option value="review">Review</option>
                            <option value="staging">Staging</option>
                            <option value="resolution">Resolution</option>
                        </select></Field
                    ><Field label="Result"
                        ><select v-model="signoff.result" class="control">
                            <option value="pass">Pass</option>
                            <option value="fail">Fail</option>
                        </select></Field
                    >
                </div>
                <Field label="Verification notes">
                    <textarea
                        v-model="signoff.note"
                        required
                        class="control"
                        placeholder="What was checked, evidence, or findings"
                    /></Field
                ><Button
                    size="sm"
                    variant="outline"
                    :disabled="signoff.processing"
                    >Record sign-off</Button
                >
            </form>
        </FormSurface>
    </section>
    <section v-if="actions.length" class="panel space-y-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
            <Zap class="size-4 text-amber-500" />Ticket actions
        </h2>
        <p class="muted">Pause, resume, or revisit the work with a reason.</p>
        <FormSurface
            v-model:open="acting"
            title="Ticket actions"
            description="Keep the team informed about why the status is changing."
            trigger="Manage ticket"
            :busy="action.processing"
        >
            <form class="space-y-4" @submit.prevent="act">
                <FormErrors :errors="action.errors" :reload-url="base" /><Field
                    label="Action"
                    ><select v-model="action.action" required class="control">
                        <option value="" disabled>Select action</option>
                        <option
                            v-for="item in actions"
                            :key="item.value"
                            :value="item.value"
                        >
                            {{ item.label }}
                        </option>
                    </select></Field
                ><Field label="Reason">
                    <textarea
                        v-model="action.reason"
                        required
                        class="control"
                        placeholder="Explain why this action is needed"
                    /></Field
                ><Button
                    size="sm"
                    variant="outline"
                    :disabled="action.processing || !action.action"
                    >{{
                        action.processing ? 'Applying…' : 'Apply action'
                    }}</Button
                >
            </form>
        </FormSurface>
    </section>
</template>

# Phase 05 — Collaboration and reliable notifications

Status: P — proposed, unapproved. Depends on Phases 01–02 (active accounts, visibility, ticket events and transitions). Phase 03 security predicates apply whenever enabled; Phase 04 is not required. Goal: collaborate on work and deliver updates without losing history or leaking content.

## Evidence and scope

D inspiration: [comments and audiences](https://support.atlassian.com/jira-service-management-cloud/docs/talk-to-the-customer-or-team-members-from-the-new-issue-view/), [watching/mentions](https://support.atlassian.com/jira-service-management-cloud/docs/watch-and-share-issues-from-the-new-issue-view/), [customer/internal notifications](https://support.atlassian.com/jira-service-management-cloud/docs/what-notifications-do-my-customers-and-service-desk-team-receive/), [attachment resources](https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-attachments/). Delivery implementation and security defaults below are P.

## Functional requirements

| ID     | Expected behavior                                                                                                                                             |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P05-01 | Add permitted ticket comments with explicit audience and revision/removed-content history. Staff-only is the initial default.                                 |
| P05-02 | Upload approved attachment types/sizes privately and download only with ticket and audience permission. Track staging/ready/quarantine/deletion state.        |
| P05-03 | Create project labels and attach them to scoped tickets; support label filters.                                                                               |
| P05-04 | Mention eligible users using stored user IDs; watch/unwatch tickets. Mention and subscription never grant access.                                             |
| P05-05 | Provide paginated combined activity with safe event projections, actor/time and history. Keep administrative audits independently restricted.                 |
| P05-06 | Deliver assignment, mention, comment and transition notifications through in-app and approved email channels after commit, with retries and visible failures. |
| P05-07 | Permit per-user channel preferences without disabling required security/account communications. Track read state and delivery results.                        |

## Processes and business rules

Authorize ticket/audience → validate comment/upload/mentions → commit content, activity and outbox event → workers recheck recipient access → deliver → record result or retry. See F06 in [process flows](../PROCESS_FLOWS.md).

- P05 runs for staff. Store the audience contract now, but customer-facing publication is disabled until Phase 06 provides customer authorization. A later portal cannot infer visibility from the existence of an attachment URL.
- Separate `comments.create`, `comments.edit_own`, `comments.moderate`, `attachments.upload`, `attachments.download`, `labels.manage` and notification preference rights. Use current policy plus project/ticket security at every boundary.
- Proposed safe content is sanitized plain text or a reviewed restricted rich-text format. Validate mention IDs; do not resolve arbitrary untrusted HTML, execute embeds or fetch user-supplied URLs on the server.
- Private upload keys are generated; original filenames are metadata only. Inspect MIME/content and size against approved allowlists. Do not trust filename extensions. No SVG/HTML or executable types unless a separate policy explicitly supports them safely.
- A linked file inherits its comment's audience. Temporary objects belong to the uploader and ticket; staged/failed/quarantined files cannot be downloaded. Compensation removes expired unattached objects without erasing referenced files.
- Editing preserves prior body/audience in restricted revisions. Converting internal content to customer-visible content later requires explicit permission/confirmation; no silent conversion from a default change.
- Notification recipients are deduplicated from reporter, eligible assignee/watchers/mentions and configured events. Exclude the actor by proposed default. Recheck active identity, membership and ticket/audience access at delivery; revoked users receive no new body/preview.
- Outbox is created atomically with domain writes. Workers retry bounded failures with backoff and visible final-failure state. Internal uniqueness is event-recipient-channel; external email exactly-once delivery is not guaranteed after a provider-accepted crash.
- Mail/storage failure does not roll back a committed ticket transition. Logs/audits contain safe identifiers and error codes, not raw tokens, full private message bodies or storage credentials.

## Starting schema

P: inherit project/ticket composite FKs, restricted history and UTC occurrence/record times.

| Tables                                 | Starting data / relationship                                                                      |
| -------------------------------------- | ------------------------------------------------------------------------------------------------- |
| comments, comment_revisions            | Author/editor, ticket/project, audience, body, lock, timestamps and tombstone                     |
| attachments                            | Ticket/comment/uploader, private object key, original name, detected type, bytes, audience, state |
| labels, ticket_labels                  | Project-owned catalog and scoped joins                                                            |
| ticket_watchers, comment_mentions      | Unique user-ticket and user-comment associations                                                  |
| outbox_events                          | Immutable event identity/audience, safe payload, availability and state                           |
| notifications, notification_deliveries | Recipient/read state and channel attempts, retry/error result                                     |
| user_notification_preferences          | User, event category, channel, enabled; unique(user,event,channel)                                |

Detailed proposed fields: [domain model](../DOMAIN_MODEL.md#phase-05--collaboration-and-delivery). Framework jobs/failed-jobs tables may support the queue; they are infrastructure, not copied Jira entities.

## Deliverables and acceptance criteria

Deliver staff collaboration with private files, scoped activity and testable retryable delivery. Laravel filesystem, database queue and mail abstractions suffice. Choose a real email transport and file policy before enabling uploads/mail. No new package is assumed; a scanner is an optional dependency only if the approved policy requires it.

- AC01: A permitted staff user comments/mentions/watches; a project outsider or restricted-ticket nonviewer cannot read content, counts, mention suggestions or files.
- AC02: Invalid MIME/oversize uploads fail without downloadable metadata; staged/quarantined files cannot be retrieved; cross-ticket attachment URLs are rejected.
- AC03: Comment edits retain a restricted revision and stale edit detection. Audience changes do not silently expose internal material.
- AC04: Assignment transaction rollback creates no notification. Mail failure leaves the committed ticket intact and records a retry; duplicate event processing does not duplicate internal notification rows.
- AC05: Removing a member between enqueue and delivery suppresses content. Mentioning an outsider grants no access and produces no content delivery.
- AC06: User read/channel preferences work; final delivery failures are visible to authorized operators without revealing message secrets.

## Deferred work and review gate

Defer customer portal to 06; inbound email, push/chat connectors, webhooks, realtime presence/co-editing and document automation are outside this scope.

Owner decisions: comment retention/moderation, file allowlist/limits/storage/scan, recipients, actor exclusion, preferences and retry policy. Approve before coding. Fixtures include a revoked watcher, an internal attachment, a failed upload and a mail provider outage.

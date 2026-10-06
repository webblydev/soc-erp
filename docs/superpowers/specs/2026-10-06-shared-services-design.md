# Shared services — design

**Phase:** 1, sub-project 3 of 4 (Foundation core → Admin screens → **Shared services** → Catalog)
**Source:** docs/01 §3.9, §3.10, §3.12, §5.14, §9, FD-BR-08, FD-BR-09, FD-AC-07
**Status:** Built on branch `admin-screens`, 2026-10-06. The user asked for the build to go ahead without review stops; the decisions below were made on their behalf and are open to change.

## 1. Goal

Give every module three shared services: polymorphic attachments with versions and signed downloads, polymorphic quick notes, and notification delivery (in-app inbox and email) that respects the per-user preferences built in sub-project 2. The first host record is the admin user form (user's choice); CRM and later modules reuse the same components.

## 2. Decisions

| # | Decision |
|---|---|
| S1 | **Parent access contract.** A model that carries attachments or notes implements `App\Support\Collaboration\Collaborative` with `isViewableBy(User $user): bool`, and uses the `HasAttachments` / `HasNotes` traits. Reading, downloading, uploading and noting all require `isViewableBy` on the parent; the action permissions (`attachments.upload`, `notes.create`, …) come on top. `User` is viewable by holders of `admin.users.view`. |
| S2 | **Files.** A new `private` disk (local driver, `storage/app/private`, never served). Path `attachments/{morph alias}/{parent id}/{uuid}.{ext}`. Downloads go through `GET attachments/{attachment}/download`, a temporary signed URL (30 minutes) behind the `app` middleware group, which re-checks `isViewableBy` on the parent, so a copied URL returns 403 for a user without access (FD-AC-07). |
| S3 | **Validation (FD-BR-08).** Extension must be in the global list (pdf, jpg, jpeg, png, webp, dwg, dxf, xlsx, docx, zip). When a document type is chosen, its `allowed_mimes` (a list of extensions) narrows the list and its `max_size_mb` sets the limit; otherwise the limit is 20 MB. |
| S4 | **Versions.** "Replace" uploads a new row with `version = old + 1` and `replaces_attachment_id = old id`, copying the document type and title. Lists show only the latest version of each file (no live row replaces it); earlier versions are downloadable from the row's actions sheet. |
| S5 | **Delete.** Soft delete. Allowed with `attachments.delete_any`, or with `attachments.delete_own` for the uploader within 24 hours of upload. Files stay on disk (soft delete). Notes: `notes.delete_any`, or `notes.delete_own` for the author (no time limit; the doc sets none). |
| S6 | **Notes** support add, pin/unpin and delete. Pinned notes sort first, then newest first. Pinning needs `notes.create` and parent access. No editing (doc: add/pin/delete). |
| S7 | **`document_types` lookup** with `allowed_mimes` JSON and `max_size_mb`, registered in the master-data registry under `admin.master_data`. The registry gains a `list` extra-field type: edited as comma-separated text, stored as a JSON array of lowercase tokens. Seeded with the 12 types in docs/01 §3.9 as system rows. |
| S8 | **Notification base class.** `App\Support\Notifications\PreferenceNotification` (queued, after commit) declares its key; `via()` keeps only channels listed for that key in `config/notifications.php`, enabled in the user's preferences, and globally available: `mail` needs an email address and `notifications.email_enabled`; `sms` is never sent (no gateway yet, docs/01 open question 2). |
| S9 | **Notifications sent.** `user.created` (mail) from `CreateUser` when the user has an email; it names the username and sign-in link and never the password. `user.password_reset` (mail, database) from `UpdateUser` when an admin sets another user's password. `security.login_new_ip` (mail, database) on login for users holding a role in `notifications.new_ip_roles` (`accountant`, `finance_manager`) when the IP has no earlier successful sign-in and the user has signed in before. Adding `database` to the last two is a deviation from docs/01 §9 so the inbox has content. |
| S10 | **Inbox.** Laravel `notifications` table. The top-bar bell (desktop) and a mobile top-bar action become a Livewire bell with an unread count linking to `notifications` — a full-screen list (`x-ui.item` rows, All / Unread segmented control, load more, mark one read on open, mark all read). |

## 3. Data

- `document_types`: lookup columns + `allowed_mimes` JSON nullable + `max_size_mb` SMALLINT default 20.
- `attachments`: per docs/01 §3.9 ([STD] = `auditColumns` + timestamps), `softDeletes`, index (`attachable_type`, `attachable_id`). Auditable.
- `notes`: `id`, `notable_type`, `notable_id`, `body` TEXT, `is_pinned`, `created_by`, timestamps, `softDeletes`, index (`notable_type`, `notable_id`). Auditable.
- `notifications`: Laravel default.
- Morph aliases: `attachment`, `note`, `document_type`.

## 4. Units

- Actions (`app/Modules/Foundation/Actions`): `UploadAttachment` (also uploads a new version when given the file it replaces), `DeleteAttachment`, `AddNote`, `SetNotePinned`, `DeleteNote`. Each authorizes against the actor, validates, throws `ValidationException` / `AuthorizationException`, and writes in `DB::transaction()`.
- Livewire (`Foundation\Livewire\Shared`): `Attachments` and `Notes` panels, registered as `foundation.attachments` and `foundation.notes` and mounted with `:model`; the parent is kept as a locked morph alias and id (`InteractsWithCollaborativeParent`) and re-checked on every request. `Foundation\Livewire\Notifications\Bell` (`foundation.notifications.bell`) and `Notifications\Index` (route `notifications.index`).
- Notifications in `app/Modules/Foundation/Notifications`: `AccountCreated`, `PasswordResetByAdmin`, `NewIpSignIn`; the new-IP check is the `NotifyNewIpSignIn` listener on `Login`.
- Download route closure in `routes/modules/foundation.php` (`attachments.download`, `signed` middleware).
- `config/livewire.php` temporary upload ceiling raised to 100 MB; the real limits are applied by `UploadAttachment`. PHP's `upload_max_filesize` / `post_max_size` must allow the largest document type on the server.

## 5. Mobile

Panels are list rows with a chevron/actions button; upload, replace, versions and delete open in the existing bottom sheet (`x-shell.sheet`). Tap targets ≥ 44 px. The inbox is a full-screen list reached from the bell in the mobile top bar.

## 6. Testing

Feature tests: upload rules (extension, size, document type limits), versions and latest-only listing, delete rules (own within 24 h, any), signed download 200 / 403 / unsigned 403, notes add/pin/delete rules, panels render on the user form, notification channel filtering (preferences, email setting, missing email), each trigger, inbox read/mark-all.

## 7. Not in scope

Daily digest (`notifications.daily_digest_time`; its content is CRM's `SendDailyDigest`, docs/03), SMS gateway (dropped: all staff have email), editing notes, purging attachment files, image thumbnails.

Added after the build (2026-10-06): the `foundation.history` audit timeline panel and the `<x-lookup-select>` component (docs/01 §5.14).

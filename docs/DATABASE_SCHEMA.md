# Database Schema Reference
## ACTUAL Column Names - USE THESE EXACTLY

### users
- id (PK)
- google_user_id
- email
- name
- picture
- locale
- created_at
- updated_at

### user_tokens
- id (PK)
- user_id (FK → users.id)
- encrypted_tokens
- key_version
- created_at
- updated_at

### tasks
- id (PK)
- user_id (FK → users.id)
- title
- description
- due_date
- status (ENUM: 'pending', 'completed')
- is_shared (TINYINT 0/1)
- google_event_id
- created_at
- updated_at

### schedule_requests
- id (PK)
- sender_id (FK → users.id)
- recipient_id (FK → users.id)
- title
- description
- status (ENUM: 'pending', 'accepted', 'declined')
- accepted_slot_id (FK → schedule_request_slots.id, NULLABLE)
- created_at
- updated_at

**Workflow:**
- status='pending' → Show Accept/Decline buttons
- status='accepted' + accepted_slot_id=NULL → Show "Schedule" button (Pending Schedule state)
- status='accepted' + accepted_slot_id=NOT NULL → Scheduled to calendar (completed)
- status='declined' → Closed, no actions

**API Endpoints:**
- POST /schedule/{id}/accept → Set status='accepted'
- POST /schedule/{id}/decline → Set status='declined'
- POST /schedule/{id}/schedule → Set accepted_slot_id (requires slot_id in body)
  - If slots exist: User picks slot, sets accepted_slot_id
  - If no slots: Redirect to /calendar with query params to create event

### schedule_request_slots
- id (PK)
- request_id (FK → schedule_requests.id)
- start_at (DATETIME)
- end_at (DATETIME)
**NOTE: NO created_at, NO updated_at, NO is_selected**

### push_subscriptions
- id (PK)
- user_id (FK → users.id)
- endpoint
- p256dh
- auth
- user_agent
- created_at
- updated_at

### audit_logs
- id (PK)
- user_id (FK → users.id, NULLABLE)
- event_type
- ip_address
- user_agent
- details
- created_at
**NOTE: NO updated_at**

### calendar_events
- id (PK)
- user_id (FK → users.id)
- title
- description
- start_at (DATETIME)
- end_at (DATETIME)
- recurrence_type (ENUM: 'daily', 'weekly', 'monthly', 'yearly', NULLABLE)
- recurrence_interval (INT, NULLABLE)
- recurrence_end (DATE, NULLABLE)
- schedule_request_id (FK → schedule_requests.id, NULLABLE)
- created_at
- updated_at

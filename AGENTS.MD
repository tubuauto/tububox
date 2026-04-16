# AGENTS.md

## 1. Project Overview

This project is a **Delivery Fulfillment SaaS Platform**.

It is a multi-tenant, API-first system that handles:

- Delivery request intake (API / manual / import)
- Dispatching drivers
- Pickup → delivery → completion workflow
- Tracking & logs
- COD (cash on delivery)
- Webhook callbacks

This is NOT a simple app.
This is a **platform-level infrastructure system**.

---

## 2. Source of Truth

Before making any changes, ALWAYS read:

- docs/MASTER_SPEC.md
- docs/API_SPEC.md
- docs/DB_SCHEMA.sql
- README.md

These files define:
- business rules
- API design
- database schema
- scope of MVP

If conflicts exist:
MASTER_SPEC.md has highest priority.

---

## 3. Tech Stack (STRICT)

- PHP 8.x
- PostgreSQL
- API-first architecture
- Monolithic app (for now)
- Web + Mobile responsive UI

DO NOT introduce:
- Other languages
- Microservices
- Heavy frameworks without justification

---

## 4. Architecture Rules

### MUST follow:

- API-first: UI must call APIs
- All APIs under `/api/v1`
- Business logic in Service layer
- Controllers must be thin
- Repository handles DB access
- No business logic inside Views

### Structure:

- app/Controllers
- app/Services
- app/Domain
- app/Repositories
- app/Middlewares
- app/Views
- routes/
- database/migrations/

---

## 5. Multi-Tenant Rules

- Every business table MUST include `tenant_id`
- Always filter by tenant_id
- Never leak data across tenants
- Admin can bypass tenant scope

---

## 6. Delivery Domain Rules

### Delivery is NOT Order

External orders ≠ internal deliveries

### Status must follow state machine:

Allowed flow:

- pending → assigned
- assigned → driver_arriving_pickup
- driver_arriving_pickup → picked_up
- picked_up → in_transit
- in_transit → arrived
- arrived → signed
- signed → completed

Exception flows:

- pending → cancelled
- assigned → cancelled
- any → failed

DO NOT allow invalid transitions.

---

## 7. Idempotency Rules

Creating delivery must support idempotency.

Use:
- idempotency_key
- source_order_no
- external_ref

If duplicate request detected:
→ return existing delivery
→ DO NOT create new one

---

## 8. COD Rules

- COD is execution only
- Platform does NOT own transaction
- Record:
  - expected amount
  - collected amount
  - method
  - proof
- COD must be logged in separate table

---

## 9. API Rules

- JSON only
- Standard response format:

Success:

{
"success": true,
"message": "...",
"data": {}
}


Error:

{
"success": false,
"message": "...",
"errors": {}
}


- Validate all inputs
- Return proper HTTP status codes

---

## 10. Webhook Rules

- Trigger on status changes
- Log all webhook attempts
- Retry failed webhook calls (future improvement)
- Payload must match API_SPEC.md

---

## 11. UI Rules

UI is NOT optional.

### Must be:

- Clean
- Modern
- Light theme
- Responsive (PC + mobile)
- Mobile should feel like an app

### Required:

- Dashboard cards
- Tables with filters
- Status badges
- Clear forms
- Large touch areas for mobile

DO NOT:
- Build ugly admin panels
- Skip styling
- Ignore mobile

---

## 12. Development Workflow

Always work in phases:

### Phase pattern:

1. Plan
2. Implement
3. Validate
4. Summarize

### After each phase output:

- What was built
- Files created/modified
- How to test
- Next step

---

## 13. Code Quality

- Use strict typing
- Write clean, readable code
- Add comments for core logic
- Avoid duplication
- Keep functions small and focused

---

## 14. Performance & Safety

- Add indexes for:
  - tenant_id
  - status
  - created_at
- Avoid N+1 queries
- Sanitize inputs
- Prevent SQL injection

---

## 15. DO NOT

- Do not rewrite entire project at once
- Do not ignore spec files
- Do not skip validation
- Do not mix business logic into controllers
- Do not break API contracts

---

## 16. Execution Instruction

When starting:

1. Read all docs
2. Enter plan mode
3. Propose phased plan
4. Execute phase 1 only
5. Wait for confirmation if needed

---

## 17. Goal

Build a **production-grade Delivery SaaS platform**, not a demo.

Focus on:
- correctness
- structure
- scalability
- clarity
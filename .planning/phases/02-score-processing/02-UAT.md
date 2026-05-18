---
status: passed
phase: 02-score-processing
source: 02-01-SUMMARY.md, 02-02-SUMMARY.md, api-tests.http
started: 2026-05-18T16:30:00Z
updated: 2026-05-18T18:43:00Z
---

## Tests

### 1. Create a Contact (Prerequisite)
expected: POST /api/contacts returns 201 with status "pending" and score 0.
result: [passed] Created "João Silva" (joao@gmail.com, 11999999999) → 201, status "pending", score 0.

### 2. Trigger Score Processing
expected: POST /api/contacts/{id}/process-score returns 202 with "Score processing queued."
result: [passed] Returned 202 with `{"message":"Score processing queued.","contact_id":1}`.

### 3. Contact Becomes Active with Calculated Score
expected: After queue processes, contact status "active" with calculated score.
result: [passed] João Silva → status "active", score 30 (gmail=free +0, multi-word +10, SP DDD +20).

### 4. Score Varies by Email Domain, Name Length, Phone DDD
expected: Different inputs yield different scores per strategy rules.
result: [passed]
  - Maria (corporate+multi-word+SP DDD) → score 50 ✓
  - Ana (corporate+.br+multi-word+RJ DDD) → score 50 ✓
  - Carlos (hotmail+single+invalid DDD) → score 0 ✓

### 5. Score Process Returns 404 for Non-Existent Contact
expected: POST /api/contacts/9999/process-score returns 404.
result: [passed] Returned 404 with `{"message":"Not Found"}`.

### 6. CRUD Operations (Create, Read, Update, Delete, Validate)
expected: REST endpoints work correctly with validation and soft deletes.
result: [passed]
  - Validation errors → 422 ✓
  - Duplicate email → 422 ✓
  - Show contact → 200 ✓
  - 404 for missing → 404 ✓
  - Update contact → 200 ✓
  - Soft delete → 204 (GET after delete → 404) ✓
  - List (no soft-deleted) → 3 items ✓

## Summary

total: 6
passed: 6
issues: 0
pending: 0
skipped: 0
blocked: 0

## Notes

- Score validation matches Strategy implementation:
  - EmailDomainScoringStrategy: excludes gmail/hotmail/yahoo from corporate bonus
  - NameLengthScoringStrategy: ≥2 words → +10
  - PhoneDddScoringStrategy: DDD 11-19 → +20, 20+ → +10, <11 → 0
- Queue worker processes pending jobs; already-processed contacts reject transition
- Database: fresh migration before test session

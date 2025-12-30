INSERT INTO patient_branch_users (
    patient_id,
    branch_id,
    user_id,
    created_at,
    updated_at
)
SELECT
    id AS patient_id,
    3  AS branch_id,
    5  AS user_id,
    NOW() AS created_at,
    NOW() AS updated_at
FROM patients
WHERE id BETWEEN 416 AND 533;

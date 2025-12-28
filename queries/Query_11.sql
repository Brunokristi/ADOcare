INSERT INTO patient_branch_users (
    patient_id,
    branch_id,
    user_id,
    created_at,
    updated_at
)
SELECT
    id AS patient_id,
    2  AS branch_id,
    4  AS user_id,
    NOW() AS created_at,
    NOW() AS updated_at
FROM patients
WHERE id BETWEEN 257 AND 415;

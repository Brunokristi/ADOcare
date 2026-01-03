INSERT INTO public.patients (
    first_name,
    last_name,
    title,
    personal_number,
    sex,
    contact,
    doctor_id,
    insurance_company_id,
    address,
    city,
    zip,
    latitude,
    longitude,
    created_at,
    updated_at,
    reference_date,
    deleted_at,
    dekurz_number
)
SELECT
    /* meno is "last first" */
    CASE
        WHEN position(' ' in meno) > 0 THEN btrim(substring(meno from position(' ' in meno) + 1))
        ELSE NULL
    END AS first_name,
    btrim(split_part(meno, ' ', 1)) AS last_name,

    NULL AS title,
    rodne_cislo AS personal_number,
    pohlavie AS sex,
    NULL AS contact,

    /* doctors mapping */
    CASE odosielatel
        WHEN 1  THEN 31815
        WHEN 2  THEN 31817
        WHEN 3  THEN 31814
        WHEN 4  THEN 44210
        WHEN 5  THEN 38476
        WHEN 6  THEN 39514
        WHEN 7  THEN 42019
        WHEN 8  THEN 44152
        WHEN 10 THEN 22870
        WHEN 11 THEN 32340
        WHEN 12 THEN 41616
        WHEN 13 THEN 41629
        WHEN 14 THEN 31881
        WHEN 15 THEN 31259
        WHEN 17 THEN 31229
        WHEN 18 THEN 31229
        WHEN 19 THEN 44964
        WHEN 20 THEN 44964
        WHEN 21 THEN 31875
        WHEN 22 THEN 33029
        WHEN 23 THEN 22127
        WHEN 24 THEN 12560
        WHEN 25 THEN 43561
        WHEN 26 THEN 41588
        WHEN 27 THEN 38400
        ELSE NULL
    END AS doctor_id,

    /* insurance mapping */
    CASE poistovna
        WHEN 1 THEN 1
        WHEN 2 THEN 3
        WHEN 3 THEN 2
        ELSE NULL
    END AS insurance_company_id,

    adresa AS address,
    mesto AS city,
    NULL AS zip,
    latitude,
    longitude,
    NOW() AS created_at,
    NOW() AS updated_at,
    NULL AS reference_date,
    NULL AS deleted_at,
    cislo_dekurzu AS dekurz_number
FROM public.pacienti_import;



DELETE FROM public.pacienti_import
WHERE sestra <> 1
   OR sestra IS NULL;



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
    3  AS user_id,
    NOW() AS created_at,
    NOW() AS updated_at
FROM patients
WHERE id BETWEEN 574 AND 658;



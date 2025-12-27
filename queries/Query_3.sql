BEGIN;

-- 1) Drop rows where code contains ".-"
DELETE FROM diagnoses
WHERE code LIKE '%.-%';

-- 2) Normalize remaining codes to LDDDD (1 letter + 4 digits, pad right with 0, take first 4 digits)
WITH norm AS (
  SELECT
    id,
    (
      upper(substring(code FROM '^[A-Za-z]')) ||
      rpad(
        left(regexp_replace(code, '\D', '', 'g'), 4),
        4,
        '0'
      )
    ) AS normalized_code
  FROM diagnoses
  -- only normalize rows that start with a letter
  WHERE code ~ '^[A-Za-z]'
)
UPDATE diagnoses d
SET code = n.normalized_code
FROM norm n
WHERE d.id = n.id
  AND d.code IS DISTINCT FROM n.normalized_code;

COMMIT;

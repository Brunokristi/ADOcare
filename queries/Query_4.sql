BEGIN;

-- 1) Delete ambiguous / invalid codes
DELETE FROM diagnoses
WHERE
  code LIKE '%.-%'          -- A02.-
  OR code ~ '^[A-Za-z]\d{2}\.\d-$';  -- LDD.D-

-- 2) Normalize remaining codes to LDDDD
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
  WHERE code ~ '^[A-Za-z]'
)
UPDATE diagnoses d
SET code = n.normalized_code
FROM norm n
WHERE d.id = n.id
  AND d.code IS DISTINCT FROM n.normalized_code;

COMMIT;

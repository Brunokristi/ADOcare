SELECT
    pp.date,
    pp.patient_id,
    p.first_name,
    p.last_name,
    p.city      AS patient_city,
    p.address   AS patient_address,
    p.latitude  AS patient_lat,
    p.longitude AS patient_lng
FROM patient_points AS pp
INNER JOIN patients AS p
    ON p.id = pp.patient_id
WHERE pp.user_id = '2'
  AND pp.branch_id = '3'
  AND pp.date BETWEEN '2026-01-01' AND '2026-01-31'
  AND pp.procedure_code IN ('3439', '3440')
ORDER BY pp.date ASC;

SELECT
  pp.id AS patient_point_id,
  pp.date,
  pp.patient_id,
  p.first_name,
  p.last_name,
  p.city,
  p.address,
  p.latitude,
  p.longitude
FROM patient_points pp
JOIN patients p ON p.id = pp.patient_id
WHERE pp.user_id = 2
  AND pp.branch_id = 3
  AND pp.date = '2026-01-01'
  AND pp.procedure_code IN ('3439','3440')
ORDER BY pp.id;

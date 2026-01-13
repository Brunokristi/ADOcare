SELECT
  pp.id,
  pp.date,
  b.latitude  AS branch_lat,
  b.longitude AS branch_lng,
  p.latitude  AS patient_lat,
  p.longitude AS patient_lng,
  pcp.price
FROM patient_points AS pp
JOIN patients  AS p ON p.id = pp.patient_id
JOIN branches  AS b ON b.id = pp.branch_id
JOIN procedures AS proc
  ON proc.code = '0000'
JOIN procedure_company_prices AS pcp
  ON pcp.procedure_id = proc.id
 AND pcp.insurance_company_id = p.insurance_company_id
WHERE pp.user_id = 2
  AND pp.branch_id = 2
  AND p.insurance_company_id = 3
  AND pp.date BETWEEN '2025-12-01' AND '2025-12-31'
  AND pp.procedure_code IN ('3439', '3440')
ORDER BY pp.date;


SELECT id, code
FROM public.procedures
WHERE code = '0000';





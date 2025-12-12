SELECT
    c.ico,
    d.pzs,
    d.zpr,
    p.first_name,
    p.last_name,
    p.personal_number,
    pp.diagnosis_code,
    pp.procedure_code,
    p.reference_date,
    pp.date                    AS point_date,
    pp.quantity,
    pcp.price
FROM patient_branch_users pbu
JOIN patients p
  ON p.id = pbu.patient_id
JOIN doctors d
  ON d.id = p.doctor_id
JOIN users u
  ON u.id = pbu.user_id
JOIN branches b
  ON b.id = pbu.branch_id
JOIN company c
  ON c.id = b.company_id
JOIN patient_points pp
  ON pp.patient_id = p.id
JOIN procedure_company_prices pcp
  ON pcp.procedure_id         = pp.procedure_id
 AND pcp.insurance_company_id = p.insurance_company_id
WHERE pbu.user_id  = 1
  AND pbu.branch_id = 1
  AND pp.date BETWEEN '2025-12-01' AND '2025-12-31'
  AND p.insurance_company_id = 1
ORDER BY pp.date;

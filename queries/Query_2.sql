SELECT setval(
  pg_get_serial_sequence('procedures', 'id'),
  (SELECT COALESCE(MAX(id), 1) FROM procedures)
);

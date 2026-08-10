
INSERT INTO role_permissions (tenant_id, role_id, category, form_name, sub_permission, allowed, created_at, updated_at)
SELECT rp.tenant_id, rp.role_id, rp.category, 'Our Standing', rp.sub_permission, rp.allowed, NOW(), NOW()
FROM role_permissions rp
WHERE rp.form_name = 'Customer Ledger'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp2
      WHERE rp2.tenant_id = rp.tenant_id
        AND rp2.role_id = rp.role_id
        AND rp2.form_name = 'Our Standing'
        AND rp2.sub_permission = rp.sub_permission
  );

 
INSERT INTO role_permissions (tenant_id, role_id, category, form_name, sub_permission, allowed, created_at, updated_at)
SELECT rp.tenant_id, rp.role_id, rp.category, 'Daily Product Sale', rp.sub_permission, rp.allowed, NOW(), NOW()
FROM role_permissions rp
WHERE rp.form_name = 'Daily Sale Report'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp2
      WHERE rp2.tenant_id = rp.tenant_id
        AND rp2.role_id = rp.role_id
        AND rp2.form_name = 'Daily Product Sale'
        AND rp2.sub_permission = rp.sub_permission
  );
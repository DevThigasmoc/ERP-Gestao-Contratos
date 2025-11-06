ALTER TABLE proposals
    ADD COLUMN company_id INT NULL AFTER id;

UPDATE proposals p
SET company_id = (
    SELECT u.company_id FROM users u WHERE u.id = p.user_id LIMIT 1
)
WHERE company_id IS NULL;

ALTER TABLE proposals
    MODIFY COLUMN company_id INT NOT NULL,
    ADD INDEX idx_proposals_company (company_id),
    ADD CONSTRAINT fk_proposals_company FOREIGN KEY (company_id) REFERENCES companies(id);

ALTER TABLE contracts
    ADD COLUMN company_id INT NULL AFTER id;

UPDATE contracts c
SET company_id = (
    SELECT p.company_id FROM proposals p WHERE p.id = c.proposal_id LIMIT 1
)
WHERE company_id IS NULL;

ALTER TABLE contracts
    MODIFY COLUMN company_id INT NOT NULL,
    ADD INDEX idx_contracts_company (company_id),
    ADD CONSTRAINT fk_contracts_company FOREIGN KEY (company_id) REFERENCES companies(id);

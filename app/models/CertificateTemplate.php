<?php
/**
 * Certificate Template Model
 */

class CertificateTemplate {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get all templates
     */
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM certificate_templates ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get active templates
     */
    public function getActive() {
        $stmt = $this->db->query("SELECT * FROM certificate_templates WHERE is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM certificate_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update template
     */
    public function update($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE certificate_templates 
            SET template_name = ?, company_name = ?, company_logo = ?, 
                template_html = ?, template_css = ?, variables = ?, is_active = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['template_name'],
            $data['company_name'],
            $data['company_logo'] ?? null,
            $data['template_html'],
            $data['template_css'] ?? null,
            $data['variables'] ?? null,
            $data['is_active'] ?? 1,
            $id
        ]);
    }
    
    /**
     * Toggle active status
     */
    public function toggleActive($id) {
        $stmt = $this->db->prepare("UPDATE certificate_templates SET is_active = NOT is_active WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

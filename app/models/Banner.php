<?php
/**
 * Banner Model - Manages promotional banners
 */

class Banner {
    private $conn;
    private $table = 'promotional_banners';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all banners (with optional active filter)
     */
    public function getAll($activeOnly = false) {
        try {
            $sql = "SELECT * FROM {$this->table}";
            
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            
            $sql .= " ORDER BY display_order ASC, created_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Banner Model Error (getAll): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get banner by ID
     */
    public function getById($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Banner Model Error (getById): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new banner
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO {$this->table} 
                    (title, description, image_path, link_url, display_order, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                $data['title'] ?? null,
                $data['description'] ?? null,
                $data['image_path'],
                $data['link_url'] ?? null,
                $data['display_order'] ?? 0,
                $data['is_active'] ?? 1
            ]);
        } catch (PDOException $e) {
            error_log("Banner Model Error (create): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update banner
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE {$this->table} SET 
                    title = ?, 
                    description = ?, 
                    image_path = ?, 
                    link_url = ?, 
                    display_order = ?, 
                    is_active = ?
                    WHERE id = ?";
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                $data['title'] ?? null,
                $data['description'] ?? null,
                $data['image_path'],
                $data['link_url'] ?? null,
                $data['display_order'] ?? 0,
                $data['is_active'] ?? 1,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Banner Model Error (update): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete banner
     */
    public function delete($id) {
        try {
            // Get banner to delete image file
            $banner = $this->getById($id);
            
            if ($banner && !empty($banner['image_path'])) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $banner['image_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            $sql = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$id]);
            
        } catch (PDOException $e) {
            error_log("Banner Model Error (delete): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle banner status
     */
    public function toggleStatus($id) {
        try {
            $sql = "UPDATE {$this->table} SET is_active = NOT is_active WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Banner Model Error (toggleStatus): " . $e->getMessage());
            return false;
        }
    }
}

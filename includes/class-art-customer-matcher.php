<?php
/**
 * ART Customer Matcher
 *
 * Intelligent customer matching to prevent duplicates
 * - Searches Amelia customers by email, phone, and name
 * - Scores matches (0-100)
 * - Categorizes results (exact/high/possible)
 *
 * @package AmeliaCPTSync
 * @subpackage ART
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Amelia_Customer_Matcher {
    
    /**
     * Find and score customer matches
     *
     * @param array $search_data Search parameters (email, phone, first_name, last_name)
     * @return array Categorized matches (exact, high, possible)
     */
    public function find_matches($search_data) {
        // Validate input
        if (empty($search_data['email']) && empty($search_data['phone']) && empty($search_data['first_name'])) {
            return array(
                'exact' => null,
                'high' => array(),
                'possible' => array()
            );
        }
        
        // Search Amelia customers
        $candidates = $this->search_customers($search_data);
        
        if (empty($candidates)) {
            return array(
                'exact' => null,
                'high' => array(),
                'possible' => array()
            );
        }
        
        // Score each candidate
        $scored = array();
        foreach ($candidates as $candidate) {
            $score = $this->calculate_score($candidate, $search_data);
            
            if ($score >= 50) { // Minimum threshold
                $scored[] = array(
                    'customer' => $candidate,
                    'score' => $score,
                    'reasons' => $this->get_match_reasons($candidate, $search_data, $score)
                );
            }
        }
        
        // Sort by score (highest first)
        usort($scored, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Categorize results
        $exact = null;
        $high = array();
        $possible = array();
        
        foreach ($scored as $match) {
            if ($match['score'] >= 100) {
                $exact = $match; // Only one exact match
                break;
            } elseif ($match['score'] >= 75) {
                $high[] = $match;
            } elseif ($match['score'] >= 50) {
                $possible[] = $match;
            }
        }
        
        amelia_cpt_sync_debug_log('ART Customer Matcher: Found matches', array(
            'exact' => $exact ? 'Customer #' . $exact['customer']['id'] : 'none',
            'high_count' => count($high),
            'possible_count' => count($possible)
        ));
        
        return compact('exact', 'high', 'possible');
    }
    
    /**
     * Search Amelia customers database
     *
     * @param array $search_data Search parameters
     * @return array Customer results
     */
    private function search_customers($search_data) {
        global $wpdb;
        
        // Normalize inputs
        $email = !empty($search_data['email']) ? strtolower(trim($search_data['email'])) : '';
        $phone_digits = !empty($search_data['phone']) ? preg_replace('/\D/', '', $search_data['phone']) : '';
        $first_name = sanitize_text_field($search_data['first_name'] ?? '');
        $last_name = sanitize_text_field($search_data['last_name'] ?? '');
        $full_name = trim($first_name . ' ' . $last_name);
        
        // Build WHERE conditions
        $conditions = array();
        $params = array();
        
        if (!empty($email)) {
            $conditions[] = "LOWER(email) = %s";
            $params[] = $email;
        }
        
        if (!empty($phone_digits)) {
            // Match phone with digits only (handles different formats)
            $conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), '(', ''), ')', ''), ' ', '') LIKE %s";
            $params[] = '%' . $phone_digits . '%';
        }
        
        if (!empty($first_name)) {
            $conditions[] = "firstName LIKE %s";
            $params[] = '%' . $first_name . '%';
        }
        
        if (!empty($last_name)) {
            $conditions[] = "lastName LIKE %s";
            $params[] = '%' . $last_name . '%';
        }
        
        if (!empty($full_name)) {
            $conditions[] = "CONCAT(firstName, ' ', lastName) LIKE %s";
            $params[] = '%' . $full_name . '%';
        }
        
        if (empty($conditions)) {
            return array();
        }
        
        // Build and execute query
        $where_clause = implode(' OR ', $conditions);
        $sql = "SELECT id, firstName, lastName, email, phone, countryPhoneIso, note 
                FROM {$wpdb->prefix}amelia_users 
                WHERE type = 'customer' 
                AND ({$where_clause})
                LIMIT 20";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        
        amelia_cpt_sync_debug_log('ART Customer Matcher: Search returned ' . count($results) . ' candidates');
        
        return $results;
    }
    
    /**
     * Calculate match score (0-100)
     *
     * @param array $candidate Amelia customer
     * @param array $search_data Form submission data
     * @return int Score (0-100)
     */
    private function calculate_score($candidate, $search_data) {
        $score = 0;
        
        // Email match (100 points for exact)
        if (!empty($search_data['email']) && !empty($candidate['email'])) {
            if (strcasecmp($candidate['email'], $search_data['email']) === 0) {
                $score += 100; // Exact email = perfect match
            }
        }
        
        // Phone match (50 points)
        if (!empty($search_data['phone']) && !empty($candidate['phone'])) {
            $phone_candidate = preg_replace('/\D/', '', $candidate['phone']);
            $phone_search = preg_replace('/\D/', '', $search_data['phone']);
            
            if (!empty($phone_candidate) && $phone_candidate === $phone_search) {
                $score += 50;
            }
        }
        
        // Name similarity (0-50 points)
        $name_candidate = strtolower(trim(($candidate['firstName'] ?? '') . ' ' . ($candidate['lastName'] ?? '')));
        $name_search = strtolower(trim(($search_data['first_name'] ?? '') . ' ' . ($search_data['last_name'] ?? '')));
        
        if (!empty($name_candidate) && !empty($name_search)) {
            // Use similar_text for percentage similarity
            similar_text($name_candidate, $name_search, $percent);
            $score += round($percent / 2); // 0-50 points
        }
        
        return round($score);
    }
    
    /**
     * Get human-readable match reasons
     *
     * @param array $candidate Amelia customer
     * @param array $search_data Form submission data
     * @param int $score Match score
     * @return array List of match reasons
     */
    private function get_match_reasons($candidate, $search_data, $score) {
        $reasons = array();
        
        // Check email
        if (!empty($search_data['email']) && !empty($candidate['email'])) {
            if (strcasecmp($candidate['email'], $search_data['email']) === 0) {
                $reasons[] = 'Email match';
            }
        }
        
        // Check phone
        if (!empty($search_data['phone']) && !empty($candidate['phone'])) {
            $phone_candidate = preg_replace('/\D/', '', $candidate['phone']);
            $phone_search = preg_replace('/\D/', '', $search_data['phone']);
            
            if (!empty($phone_candidate) && $phone_candidate === $phone_search) {
                $reasons[] = 'Phone match';
            }
        }
        
        // Check name similarity
        $name_candidate = strtolower(trim(($candidate['firstName'] ?? '') . ' ' . ($candidate['lastName'] ?? '')));
        $name_search = strtolower(trim(($search_data['first_name'] ?? '') . ' ' . ($search_data['last_name'] ?? '')));
        
        if (!empty($name_candidate) && !empty($name_search)) {
            similar_text($name_candidate, $name_search, $percent);
            
            if ($percent >= 90) {
                $reasons[] = sprintf('Name match (%d%%)', round($percent));
            } elseif ($percent >= 70) {
                $reasons[] = sprintf('Similar name (%d%%)', round($percent));
            }
        }
        
        // If no specific reasons but has score, add generic
        if (empty($reasons) && $score >= 50) {
            $reasons[] = sprintf('Partial match (%d%%)', $score);
        }
        
        return $reasons;
    }
}


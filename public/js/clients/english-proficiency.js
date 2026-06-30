/**
 * English Proficiency Level Detection System
 * Department of Home Affairs (DHA) Requirements Implementation
 * 
 * CRITICAL DATE: August 7, 2025
 * English language test requirements changed significantly on this date.
 * This system automatically detects test dates and applies the correct requirements.
 * 
 * This file contains all functions for detecting and displaying English proficiency levels
 * based on official DHA requirements for Australian visa applications.
 * 
 * Supported Test Types:
 * - IELTS Academic/General Training
 * - PTE Academic
 * - TOEFL iBT
 * - CAE (Cambridge Advanced)
 * - OET (Occupational English Test)
 * - CELPIP General
 * - MET (Michigan English Test)
 * - LANGUAGECERT Academic
 * 
 * Proficiency Levels:
 * - Superior English (20 points)
 * - Proficient English (10 points)
 * - Competent English (0 points)
 * - Vocational English (0 points)
 * - Functional English (0 points)
 * - Below Functional English (0 points)
 */

// ===== HELPER FUNCTIONS =====

/**
 * Validate test date format (dd/mm/yyyy)
 * @param {string} dateString - Date string to validate
 * @returns {boolean} - True if valid format
 */
function isValidDateFormat(dateString) {
    const dateRegex = /^\d{2}\/\d{2}\/\d{4}$/;
    if (!dateRegex.test(dateString)) {
        return false;
    }
    
    const [day, month, year] = dateString.split('/').map(Number);
    const date = new Date(year, month - 1, day);
    
    return date.getDate() === day && 
           date.getMonth() === month - 1 && 
           date.getFullYear() === year;
}

// ===== MAIN ENGLISH PROFICIENCY LEVEL DETECTION FUNCTION =====

/**
 * Main function to detect English proficiency level based on test type and scores
 * @param {string} testType - The type of English test (IELTS, PTE, TOEFL, etc.)
 * @param {object} scores - Object containing listening, reading, writing, speaking, overall scores
 * @param {string|null} testDate - Test date in dd/mm/yyyy format (optional)
 * @returns {object|null} - Proficiency level object with level, color, and points
 */
function detectEnglishProficiencyLevel(testType, scores, testDate = null) {
    const { listening, reading, writing, speaking, overall } = scores;
    
    // Determine if test was taken before or after 7 August 2025
    // This is the CRITICAL DATE when English language test requirements changed
    let isAfterAug2025 = false;
    if (testDate && isValidDateFormat(testDate)) {
        const parsed = new Date(testDate.split('/').reverse().join('-'));
        isAfterAug2025 = parsed >= new Date('2025-08-07');
    } else if (testDate) {
        console.warn('Invalid test date format. Expected dd/mm/yyyy format.');
    }
    
    switch (testType) {
        case 'IELTS':
        case 'IELTS_A':
            return detectIELTSLevel(listening, reading, writing, speaking, overall);
        case 'PTE':
            return detectPTELevel(listening, reading, writing, speaking, overall, isAfterAug2025);
        case 'TOEFL':
            return detectTOEFLLevel(listening, reading, writing, speaking, overall, isAfterAug2025);
        case 'CAE':
            return detectCAELevel(listening, reading, writing, speaking, overall, isAfterAug2025);
        case 'OET':
            return detectOETLevel(listening, reading, writing, speaking, overall, isAfterAug2025);
        case 'CELPIP':
            return detectCELPIPLevel(listening, reading, writing, speaking, overall, isAfterAug2025);
        case 'MET':
            return detectMETLevel(listening, reading, writing, speaking, overall, isAfterAug2025);
        case 'LANGUAGECERT':
            return detectLANGUAGECERTLevel(listening, reading, writing, speaking, overall, isAfterAug2025);
        default:
            return null;
    }
}

// ===== IELTS PROFICIENCY DETECTION =====

/**
 * Detect IELTS proficiency level
 * IELTS scores: 0-9 for each component
 */
function detectIELTSLevel(listening, reading, writing, speaking, overall) {
    const scores = [parseFloat(listening), parseFloat(reading), parseFloat(writing), parseFloat(speaking)];
    const avgScore = scores.reduce((sum, score) => sum + score, 0) / scores.length;
    
    // Superior English: 8.0 in each component
    if (scores.every(score => score >= 8.0)) {
        return { level: 'Superior English', color: '#28a745', points: 20 };
    }
    
    // Proficient English: 7.0 in each component
    if (scores.every(score => score >= 7.0)) {
        return { level: 'Proficient English', color: '#007bff', points: 10 };
    }
    
    // Competent English: 6.0 in each component
    if (scores.every(score => score >= 6.0)) {
        return { level: 'Competent English', color: '#17a2b8', points: 0 };
    }
    
    // Vocational English: 5.0 in each component (for tests after Aug 2025)
    // Vocational English: 5.0 in each component (for tests before Aug 2025) - same requirement
    if (scores.every(score => score >= 5.0)) {
        return { level: 'Vocational English', color: '#ffc107', points: 0 };
    }
    
    // Functional English: 4.5 average (same for both before and after Aug 2025)
    if (avgScore >= 4.5) {
        return { level: 'Functional English', color: '#fd7e14', points: 0 };
    }
    
    return { level: 'Below Functional English', color: '#dc3545', points: 0 };
}

// ===== PTE PROFICIENCY DETECTION =====

/**
 * Detect PTE Academic proficiency level
 * PTE scores: 10-90 for each component, 10-90 overall
 */
function detectPTELevel(listening, reading, writing, speaking, overall, isAfterAug2025) {
    const scores = [parseFloat(listening), parseFloat(reading), parseFloat(writing), parseFloat(speaking)];
    const overallScore = parseFloat(overall);
    const listeningScore = parseFloat(listening);
    const readingScore = parseFloat(reading);
    const writingScore = parseFloat(writing);
    const speakingScore = parseFloat(speaking);
    
    // Superior English: Different requirements based on test date
    if (isAfterAug2025) {
        // After Aug 2025: 69 Listening, 70 Reading, 85 Writing, 88 Speaking
        if (listeningScore >= 69 && readingScore >= 70 && writingScore >= 85 && speakingScore >= 88) {
            return { level: 'Superior English', color: '#28a745', points: 20 };
        }
    } else {
        // Before Aug 2025: 79 in each component
        if (scores.every(score => score >= 79)) {
            return { level: 'Superior English', color: '#28a745', points: 20 };
        }
    }
    
    // Proficient English: Different requirements based on test date
    if (isAfterAug2025) {
        // After Aug 2025: 58 Listening, 59 Reading, 69 Writing, 76 Speaking
        if (listeningScore >= 58 && readingScore >= 59 && writingScore >= 69 && speakingScore >= 76) {
            return { level: 'Proficient English', color: '#007bff', points: 10 };
        }
    } else {
        // Before Aug 2025: 65 in each component
        if (scores.every(score => score >= 65)) {
            return { level: 'Proficient English', color: '#007bff', points: 10 };
        }
    }
    
    // Competent English: Different requirements based on test date
    if (isAfterAug2025) {
        // After Aug 2025: 47 Listening, 48 Reading, 51 Writing, 54 Speaking
        if (listeningScore >= 47 && readingScore >= 48 && writingScore >= 51 && speakingScore >= 54) {
            return { level: 'Competent English', color: '#17a2b8', points: 0 };
        }
    } else {
        // Before Aug 2025: 50 in each component
        if (scores.every(score => score >= 50)) {
            return { level: 'Competent English', color: '#17a2b8', points: 0 };
        }
    }
    
    // Vocational English: Different requirements based on test date
    if (isAfterAug2025) {
        // After Aug 2025: 33 Listening, 36 Reading, 29 Writing, 24 Speaking
        if (listening >= 33 && reading >= 36 && writing >= 29 && speaking >= 24) {
            return { level: 'Vocational English', color: '#ffc107', points: 0 };
        }
    } else {
        // Before Aug 2025: 36 in each component
        if (scores.every(score => score >= 36)) {
            return { level: 'Vocational English', color: '#ffc107', points: 0 };
        }
    }
    
    // Functional English: 24 overall (after Aug 2025) or 30 overall (before Aug 2025)
    // According to DHA: PTE Academic has the same name but different test and scores after Aug 7, 2025
    const functionalThreshold = isAfterAug2025 ? 24 : 30;
    if (overallScore >= functionalThreshold) {
        return { level: 'Functional English', color: '#fd7e14', points: 0 };
    }
    
    return { level: 'Below Functional English', color: '#dc3545', points: 0 };
}

// ===== TOEFL PROFICIENCY DETECTION =====

/**
 * Detect TOEFL iBT proficiency level
 * TOEFL scores: 0-30 for each component, 0-120 overall
 */
function detectTOEFLLevel(listening, reading, writing, speaking, overall, isAfterAug2025) {
    const scores = [parseFloat(listening), parseFloat(reading), parseFloat(writing), parseFloat(speaking)];
    const overallScore = parseFloat(overall);
    const listeningScore = parseFloat(listening);
    const readingScore = parseFloat(reading);
    const writingScore = parseFloat(writing);
    const speakingScore = parseFloat(speaking);
    
    // Superior English: Different requirements based on test date
    if (isAfterAug2025) {
        // After Aug 2025: 26 Listening, 27 Reading, 30 Writing, 28 Speaking
        if (listeningScore >= 26 && readingScore >= 27 && writingScore >= 30 && speakingScore >= 28) {
            return { level: 'Superior English', color: '#28a745', points: 20 };
        }
    } else {
        // Before Aug 2025: 28 Listening, 29 Reading, 30 Writing, 26 Speaking
        if (listeningScore >= 28 && readingScore >= 29 && writingScore >= 30 && speakingScore >= 26) {
            return { level: 'Superior English', color: '#28a745', points: 20 };
        }
    }
    
    // Proficient English: Different requirements based on test date
    if (isAfterAug2025) {
        // After Aug 2025: 22 Listening, 22 Reading, 26 Writing, 24 Speaking
        if (listeningScore >= 22 && readingScore >= 22 && writingScore >= 26 && speakingScore >= 24) {
            return { level: 'Proficient English', color: '#007bff', points: 10 };
        }
    } else {
        // Before Aug 2025: 24 Listening, 24 Reading, 27 Writing, 23 Speaking
        if (listeningScore >= 24 && readingScore >= 24 && writingScore >= 27 && speakingScore >= 23) {
            return { level: 'Proficient English', color: '#007bff', points: 10 };
        }
    }
    
    // Competent English: Different requirements based on test date
    if (isAfterAug2025) {
        // After Aug 2025: 16 Listening, 16 Reading, 19 Writing, 19 Speaking
        if (listeningScore >= 16 && readingScore >= 16 && writingScore >= 19 && speakingScore >= 19) {
            return { level: 'Competent English', color: '#17a2b8', points: 0 };
        }
    } else {
        // Before Aug 2025: 12 Listening, 13 Reading, 21 Writing, 21 Speaking
        if (listeningScore >= 12 && readingScore >= 13 && writingScore >= 21 && speakingScore >= 21) {
            return { level: 'Competent English', color: '#17a2b8', points: 0 };
        }
    }
    
    // Vocational English: Different requirements based on test date
    if (isAfterAug2025) {
        // After Aug 2025: 8 Listening, 8 Reading, 9 Writing, 14 Speaking
        if (listening >= 8 && reading >= 8 && writing >= 9 && speaking >= 14) {
            return { level: 'Vocational English', color: '#ffc107', points: 0 };
        }
    } else {
        // Before Aug 2025: 4 Reading/Listening, 14 Writing/Speaking
        if (reading >= 4 && listening >= 4 && writing >= 14 && speaking >= 14) {
            return { level: 'Vocational English', color: '#ffc107', points: 0 };
        }
    }
    
    // Functional English: 26 total (after Aug 2025) or 32 total (before Aug 2025)
    // Note: TOEFL iBT was not approved from 26 July 2023 to 4 May 2024
    const functionalThreshold = isAfterAug2025 ? 26 : 32;
    if (overallScore >= functionalThreshold) {
        return { level: 'Functional English', color: '#fd7e14', points: 0 };
    }
    
    return { level: 'Below Functional English', color: '#dc3545', points: 0 };
}

// ===== CAE PROFICIENCY DETECTION =====

/**
 * Detect CAE (Cambridge C1 Advanced) proficiency level
 * CAE uses numeric scores on Cambridge scale (100-210+)
 * Before 7 Aug 2025: Uniform scores across components
 * On/after 7 Aug 2025: Different scores per component, Vocational & Functional not accepted
 */
function detectCAELevel(listening, reading, writing, speaking, overall, isAfterAug2025) {
    const listeningScore = parseFloat(listening);
    const readingScore = parseFloat(reading);
    const writingScore = parseFloat(writing);
    const speakingScore = parseFloat(speaking);
    const overallScore = parseFloat(overall);
    
    if (isAfterAug2025) {
        // After Aug 2025: Different scores per component
        
        // Superior English: 186 Listening, 190 Reading, 210 Writing, 208 Speaking
        if (listeningScore >= 186 && readingScore >= 190 && writingScore >= 210 && speakingScore >= 208) {
            return { level: 'Superior English', color: '#28a745', points: 20 };
        }
        
        // Proficient English: 175 Listening, 179 Reading, 193 Writing, 194 Speaking
        if (listeningScore >= 175 && readingScore >= 179 && writingScore >= 193 && speakingScore >= 194) {
            return { level: 'Proficient English', color: '#007bff', points: 10 };
        }
        
        // Competent English: 163 Listening, 163 Reading, 170 Writing, 179 Speaking
        if (listeningScore >= 163 && readingScore >= 163 && writingScore >= 170 && speakingScore >= 179) {
            return { level: 'Competent English', color: '#17a2b8', points: 0 };
        }
        
        // Vocational English: Not accepted after Aug 7, 2025
        // Functional English: Not accepted after Aug 7, 2025
        
    } else {
        // Before Aug 2025: Uniform scores across all components
        const scores = [listeningScore, readingScore, writingScore, speakingScore];
        
        // Superior English: 200 in each component
        if (scores.every(score => score >= 200)) {
            return { level: 'Superior English', color: '#28a745', points: 20 };
        }
        
        // Proficient English: 185 in each component
        if (scores.every(score => score >= 185)) {
            return { level: 'Proficient English', color: '#007bff', points: 10 };
        }
        
        // Competent English: 169 in each component
        if (scores.every(score => score >= 169)) {
            return { level: 'Competent English', color: '#17a2b8', points: 0 };
        }
        
        // Vocational English: 154 in each component
        if (scores.every(score => score >= 154)) {
            return { level: 'Vocational English', color: '#ffc107', points: 0 };
        }
        
        // Functional English: Total band >= 147
        if (overallScore >= 147) {
            return { level: 'Functional English', color: '#fd7e14', points: 0 };
        }
    }
    
    return { level: 'Below Functional English', color: '#dc3545', points: 0 };
}

// ===== OET PROFICIENCY DETECTION =====

/**
 * Detect OET (Occupational English Test) proficiency level
 * Before 7 Aug 2025: Letter grades A, B, C, D, E
 * On/after 7 Aug 2025: Numerical scores (0-500 scale)
 */
function detectOETLevel(listening, reading, writing, speaking, overall, isAfterAug2025) {
    if (isAfterAug2025) {
        // After Aug 2025: OET uses numerical scores (0-500 scale)
        const listeningScore = parseFloat(listening);
        const readingScore = parseFloat(reading);
        const writingScore = parseFloat(writing);
        const speakingScore = parseFloat(speaking);
        const overallScore = parseFloat(overall);
        
        // Superior English: 390 Listening, 400 Reading, 420 Writing, 400 Speaking
        if (listeningScore >= 390 && readingScore >= 400 && writingScore >= 420 && speakingScore >= 400) {
            return { level: 'Superior English', color: '#28a745', points: 20 };
        }
        
        // Proficient English: 350 Listening, 360 Reading, 380 Writing, 360 Speaking
        if (listeningScore >= 350 && readingScore >= 360 && writingScore >= 380 && speakingScore >= 360) {
            return { level: 'Proficient English', color: '#007bff', points: 10 };
        }
        
        // Competent English: 290 Listening, 310 Reading, 290 Writing, 330 Speaking
        if (listeningScore >= 290 && readingScore >= 310 && writingScore >= 290 && speakingScore >= 330) {
            return { level: 'Competent English', color: '#17a2b8', points: 0 };
        }
        
        // Vocational English: 220 Listening, 240 Reading, 200 Writing, 270 Speaking
        if (listeningScore >= 220 && readingScore >= 240 && writingScore >= 200 && speakingScore >= 270) {
            return { level: 'Vocational English', color: '#ffc107', points: 0 };
        }
        
        // Functional English: Overall band score >= 1020
        if (overallScore >= 1020) {
            return { level: 'Functional English', color: '#fd7e14', points: 0 };
        }
    } else {
        // Before Aug 2025: OET uses letter grades A-E
        const gradeOrder = { 'A': 5, 'B': 4, 'C': 3, 'D': 2, 'E': 1 };
        const scores = [gradeOrder[listening], gradeOrder[reading], gradeOrder[writing], gradeOrder[speaking]];
        
        // Superior English: A in each component
        if (scores.every(score => score >= 5)) {
            return { level: 'Superior English', color: '#28a745', points: 20 };
        }
        
        // Proficient English: B in each component
        // Note: Before Aug 2025, Proficient, Competent, and Vocational all require Grade B
        // The first matching level (Proficient) will be returned
        if (scores.every(score => score >= 4)) {
            return { level: 'Proficient English', color: '#007bff', points: 10 };
        }
        
        // Competent English: B in each component
        // Note: This will only be reached if Proficient check fails
        if (scores.every(score => score >= 4)) {
            return { level: 'Competent English', color: '#17a2b8', points: 0 };
        }
        
        // Vocational English: B in each component
        // Note: This will only be reached if Proficient and Competent checks fail
        if (scores.every(score => score >= 4)) {
            return { level: 'Vocational English', color: '#ffc107', points: 0 };
        }
        
        // Functional English: Not listed as an option before Aug 7, 2025
        // No threshold to check for Functional English before this date
    }
    
    return { level: 'Below Functional English', color: '#dc3545', points: 0 };
}

// ===== CELPIP PROFICIENCY DETECTION =====

/**
 * Detect CELPIP General proficiency level
 * CELPIP is ONLY accepted for tests taken on or after 7 August 2025
 * CELPIP scores: 1-12 for each component, 1-12 overall
 */
function detectCELPIPLevel(listening, reading, writing, speaking, overall, isAfterAug2025) {
    // CELPIP is only accepted for tests on or after 7 August 2025
    if (!isAfterAug2025) {
        return { level: 'CELPIP Not Accepted Before Aug 7, 2025', color: '#dc3545', points: 0 };
    }
    
    const listeningScore = parseFloat(listening);
    const readingScore = parseFloat(reading);
    const writingScore = parseFloat(writing);
    const speakingScore = parseFloat(speaking);
    const overallScore = parseFloat(overall);
    
    // Superior English: 10 Listening, 10 Reading, 12 Writing, 10 Speaking
    if (listeningScore >= 10 && readingScore >= 10 && writingScore >= 12 && speakingScore >= 10) {
        return { level: 'Superior English', color: '#28a745', points: 20 };
    }
    
    // Proficient English: 9 Listening, 8 Reading, 10 Writing, 8 Speaking
    if (listeningScore >= 9 && readingScore >= 8 && writingScore >= 10 && speakingScore >= 8) {
        return { level: 'Proficient English', color: '#007bff', points: 10 };
    }
    
    // Competent English: 7 in each component
    if (listeningScore >= 7 && readingScore >= 7 && writingScore >= 7 && speakingScore >= 7) {
        return { level: 'Competent English', color: '#17a2b8', points: 0 };
    }
    
    // Vocational English: 5 in each component
    if (listeningScore >= 5 && readingScore >= 5 && writingScore >= 5 && speakingScore >= 5) {
        return { level: 'Vocational English', color: '#ffc107', points: 0 };
    }
    
    // Functional English: Overall band score >= 5
    if (overallScore >= 5) {
        return { level: 'Functional English', color: '#fd7e14', points: 0 };
    }
    
    return { level: 'Below Functional English', color: '#dc3545', points: 0 };
}

// ===== MET PROFICIENCY DETECTION =====

/**
 * Detect MET (Michigan English Test) proficiency level
 * MET is ONLY accepted for tests taken on or after 7 August 2025
 * MET scores: 0-100 for each component, 0-100 overall
 * Superior English is NOT available for MET
 */
function detectMETLevel(listening, reading, writing, speaking, overall, isAfterAug2025) {
    // MET is only accepted for tests on or after 7 August 2025
    if (!isAfterAug2025) {
        return { level: 'MET Not Accepted Before Aug 7, 2025', color: '#dc3545', points: 0 };
    }
    
    const listeningScore = parseFloat(listening);
    const readingScore = parseFloat(reading);
    const writingScore = parseFloat(writing);
    const speakingScore = parseFloat(speaking);
    const overallScore = parseFloat(overall);
    
    // Superior English: Not available for MET
    
    // Proficient English: 61 Listening, 63 Reading, 74 Writing, 59 Speaking
    if (listeningScore >= 61 && readingScore >= 63 && writingScore >= 74 && speakingScore >= 59) {
        return { level: 'Proficient English', color: '#007bff', points: 10 };
    }
    
    // Competent English: 56 Listening, 55 Reading, 57 Writing, 48 Speaking
    if (listeningScore >= 56 && readingScore >= 55 && writingScore >= 57 && speakingScore >= 48) {
        return { level: 'Competent English', color: '#17a2b8', points: 0 };
    }
    
    // Vocational English: 49 Listening, 47 Reading, 45 Writing, 38 Speaking
    if (listeningScore >= 49 && readingScore >= 47 && writingScore >= 45 && speakingScore >= 38) {
        return { level: 'Vocational English', color: '#ffc107', points: 0 };
    }
    
    // Functional English: Overall band score >= 38
    if (overallScore >= 38) {
        return { level: 'Functional English', color: '#fd7e14', points: 0 };
    }
    
    return { level: 'Below Functional English', color: '#dc3545', points: 0 };
}

// ===== LANGUAGECERT PROFICIENCY DETECTION =====

/**
 * Detect LANGUAGECERT Academic proficiency level
 * LANGUAGECERT is ONLY accepted for tests taken on or after 7 August 2025
 * LANGUAGECERT scores: 0-100 for each component, 0-100 overall
 */
function detectLANGUAGECERTLevel(listening, reading, writing, speaking, overall, isAfterAug2025) {
    // LANGUAGECERT is only accepted for tests on or after 7 August 2025
    if (!isAfterAug2025) {
        return { level: 'LANGUAGECERT Not Accepted Before Aug 7, 2025', color: '#dc3545', points: 0 };
    }
    
    const listeningScore = parseFloat(listening);
    const readingScore = parseFloat(reading);
    const writingScore = parseFloat(writing);
    const speakingScore = parseFloat(speaking);
    const overallScore = parseFloat(overall);
    
    // Superior English: 80 Listening, 83 Reading, 89 Writing, 89 Speaking
    if (listeningScore >= 80 && readingScore >= 83 && writingScore >= 89 && speakingScore >= 89) {
        return { level: 'Superior English', color: '#28a745', points: 20 };
    }
    
    // Proficient English: 67 Listening, 71 Reading, 78 Writing, 82 Speaking
    if (listeningScore >= 67 && readingScore >= 71 && writingScore >= 78 && speakingScore >= 82) {
        return { level: 'Proficient English', color: '#007bff', points: 10 };
    }
    
    // Competent English: 57 Listening, 60 Reading, 64 Writing, 70 Speaking
    if (listeningScore >= 57 && readingScore >= 60 && writingScore >= 64 && speakingScore >= 70) {
        return { level: 'Competent English', color: '#17a2b8', points: 0 };
    }
    
    // Vocational English: 41 Listening, 44 Reading, 45 Writing, 54 Speaking
    if (listeningScore >= 41 && readingScore >= 44 && writingScore >= 45 && speakingScore >= 54) {
        return { level: 'Vocational English', color: '#ffc107', points: 0 };
    }
    
    // Functional English: Overall band score >= 38
    if (overallScore >= 38) {
        return { level: 'Functional English', color: '#fd7e14', points: 0 };
    }
    
    return { level: 'Below Functional English', color: '#dc3545', points: 0 };
}

// ===== DISPLAY AND UI FUNCTIONS =====

/**
 * Update the English proficiency display for a test score container
 * @param {HTMLElement} container - The container element for the test score form
 */
function updateEnglishProficiencyDisplay(container) {
    const testTypeSelect = container.querySelector('.test-type-selector');
    const listeningInput = container.querySelector('.listening');
    const readingInput = container.querySelector('.reading');
    const writingInput = container.querySelector('.writing');
    const speakingInput = container.querySelector('.speaking');
    const overallInput = container.querySelector('.overall_score');
    
    if (!testTypeSelect || !listeningInput || !readingInput || !writingInput || !speakingInput || !overallInput) {
        return;
    }
    
    const testType = testTypeSelect.value;
    const scores = {
        listening: listeningInput.value,
        reading: readingInput.value,
        writing: writingInput.value,
        speaking: speakingInput.value,
        overall: overallInput.value
    };
    
    // Check if all required fields are filled
    if (!testType || !scores.listening || !scores.reading || !scores.writing || !scores.speaking || !scores.overall) {
        hideProficiencyDisplay(container);
        return;
    }
    
    const proficiencyLevel = detectEnglishProficiencyLevel(testType, scores);
    
    if (proficiencyLevel) {
        showProficiencyDisplay(container, proficiencyLevel);
    } else {
        hideProficiencyDisplay(container);
    }
}

/**
 * Show the proficiency level display
 * @param {HTMLElement} container - The container element
 * @param {object} proficiencyLevel - The proficiency level object with level, color, and points
 */
function showProficiencyDisplay(container, proficiencyLevel) {
    // Remove existing display
    hideProficiencyDisplay(container);
    
    // Create proficiency display
    const displayDiv = document.createElement('div');
    displayDiv.className = 'english-proficiency-display';
    displayDiv.style.cssText = `
        margin-top: 10px;
        padding: 8px 12px;
        border-radius: 6px;
        background-color: ${proficiencyLevel.color}15;
        border-left: 4px solid ${proficiencyLevel.color};
        font-size: 13px;
        font-weight: 600;
    `;
    
    displayDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="color: ${proficiencyLevel.color};">
                ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-language') : '<i class="fas fa-language"></i>'} ${proficiencyLevel.level}
            </span>
            ${proficiencyLevel.points > 0 ? `<span style="color: #6c757d; font-size: 11px;">+${proficiencyLevel.points} points</span>` : ''}
        </div>
    `;
    
    // Insert after the form fields
    const formGroup = container.querySelector('.form-group:last-child');
    if (formGroup) {
        formGroup.parentNode.insertBefore(displayDiv, formGroup.nextSibling);
    }
}

/**
 * Hide the proficiency level display
 * @param {HTMLElement} container - The container element
 */
function hideProficiencyDisplay(container) {
    const existingDisplay = container.querySelector('.english-proficiency-display');
    if (existingDisplay) {
        existingDisplay.remove();
    }
}

// ===== EVENT LISTENERS AND INITIALIZATION =====

/**
 * Initialize English proficiency display functionality
 * This function sets up event listeners for real-time proficiency level updates
 */
function initializeEnglishProficiencyDisplay() {
    // Add event listeners to update proficiency display when scores change
    document.addEventListener('input', function(e) {
        if (e.target.matches('.listening, .reading, .writing, .speaking, .overall_score, .test-type-selector')) {
            const container = e.target.closest('.repeatable-section');
            if (container) {
                updateEnglishProficiencyDisplay(container);
            }
        }
    });
    
    // Also listen for changes on existing test score fields
    document.querySelectorAll('.test-type-selector').forEach(selector => {
        selector.addEventListener('change', function() {
            const container = this.closest('.repeatable-section');
            if (container) {
                updateEnglishProficiencyDisplay(container);
            }
        });
    });
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeEnglishProficiencyDisplay();
    calculateExistingTestScoreLevels();
});

/**
 * Calculate and display proficiency levels for existing test scores on page load
 */
function calculateExistingTestScoreLevels() {
    const calculationBoxes = document.querySelectorAll('.english-level-calculation-box');
    
    calculationBoxes.forEach((box, index) => {
        const displayElement = document.getElementById(`proficiency-level-${index}`);
        if (!displayElement) {
            return;
        }
        
        const storedLevel = box.getAttribute('data-proficiency-level');
        const storedPoints = box.getAttribute('data-proficiency-points');
        
        const testType = box.getAttribute('data-test-type');
        const listening = box.getAttribute('data-listening');
        const reading = box.getAttribute('data-reading');
        const writing = box.getAttribute('data-writing');
        const speaking = box.getAttribute('data-speaking');
        const overall = box.getAttribute('data-overall');
        const testDate = box.getAttribute('data-test-date');
        
        const hasAllScores = testType && listening && reading && writing && speaking && overall;
        
        if (!hasAllScores) {
            showIncompleteData(displayElement, storedLevel, storedPoints);
            return;
        }
        
        if (storedLevel && storedPoints !== null && storedPoints !== '') {
            showStoredProficiency(displayElement, storedLevel, storedPoints);
            return;
        }
        
        const scores = { listening, reading, writing, speaking, overall };
        const proficiencyLevel = detectEnglishProficiencyLevel(testType, scores, testDate);
        
        if (proficiencyLevel) {
            displayElement.innerHTML = `
                ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-language') : '<i class="fas fa-language"></i>'} ${proficiencyLevel.level}
                ${proficiencyLevel.points > 0 ? ` <span style="font-size: 0.8em; opacity: 0.8;">(+${proficiencyLevel.points} points)</span>` : ''}
            `;
            displayElement.style.backgroundColor = `${proficiencyLevel.color}15`;
            displayElement.style.color = proficiencyLevel.color;
            displayElement.style.border = `2px solid ${proficiencyLevel.color}`;
        } else {
            displayElement.innerHTML = (typeof crmIconAny === 'function' ? crmIconAny('circle-question-mark') : '<i class="fas fa-question-circle"></i>') + ' Unable to Calculate';
            displayElement.style.backgroundColor = '#f8d7da';
            displayElement.style.color = '#721c24';
            displayElement.style.border = '1px solid #f5c6cb';
        }
    });
}

/**
 * Display stored proficiency level/points pulled from backend
 */
function showStoredProficiency(displayElement, level, points) {
    const numericPoints = parseInt(points, 10);
    const color = getColorForLevel(level);
    
    displayElement.innerHTML = `
        ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-language') : '<i class="fas fa-language"></i>'} ${level}
        ${numericPoints > 0 ? ` <span style="font-size: 0.8em; opacity: 0.8;">(+${numericPoints} points)</span>` : ''}
    `;
    displayElement.style.backgroundColor = `${color}15`;
    displayElement.style.color = color;
    displayElement.style.border = `2px solid ${color}`;
}

/**
 * Display incomplete data warning
 */
function showIncompleteData(displayElement, storedLevel = null, storedPoints = null) {
    const hasStored = storedLevel && storedPoints !== null && storedPoints !== '';
    const numericPoints = hasStored ? parseInt(storedPoints, 10) : 0;
    const storedInfo = hasStored
        ? `<div style="margin-top: 4px; font-size: 0.85em; color: #495057;">
                Stored level: ${storedLevel}
                ${numericPoints > 0 ? `(+${numericPoints} points)` : '(0 points)'}
           </div>`
        : '';
    
    displayElement.innerHTML = `
        <div>${typeof crmIconAny === 'function' ? crmIconAny('fas fa-exclamation-triangle') : '<i class="fas fa-exclamation-triangle"></i>'} Incomplete Data</div>
        ${storedInfo}
    `;
    displayElement.style.backgroundColor = '#f8d7da';
    displayElement.style.color = '#721c24';
    displayElement.style.border = '1px solid #f5c6cb';
}

/**
 * Map English levels to consistent colors (matches backend usage)
 */
function getColorForLevel(level) {
    const map = {
        'Superior English': '#28a745',
        'Proficient English': '#007bff',
        'Competent English': '#17a2b8',
        'Vocational English': '#ffc107',
        'Functional English': '#fd7e14',
        'Below Functional English': '#dc3545'
    };
    
    return map[level] || '#6c757d';
}

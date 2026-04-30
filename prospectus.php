<?php
require_once '../includes/auth.php';
requireRole('student');
$user = getUserData($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prospectus - Academic Advising System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .prospectus-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .prospectus-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        .prospectus-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .btn-print {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }
        @media print {
            .sidebar, .btn-print, .filter-section, .chatbot-icon, .navbar {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .prospectus-card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-book-open me-2"></i>Course Prospectus</h2>
        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print Prospectus
        </button>
    </div>

    <!-- Filter Section - Auto load on change -->
    <div class="filter-section mb-4">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Year Level</label>
                <select id="yearLevel" class="form-select">
                    <option value="all">All Years</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Semester</label>
                <select id="semester" class="form-select">
                    <option value="all">All Semesters</option>
                    <option value="1">1st Semester</option>
                    <option value="2">2nd Semester</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-text text-muted w-100 text-center">
                    <i class="fas fa-sync-alt me-1"></i> Auto updates
                </div>
            </div>
        </div>
    </div>

    <div class="prospectus-card" id="prospectusContent">
        <div class="prospectus-header">
            <h4 class="mb-0"><?php echo $user['course'] == 'CE' ? 'Civil Engineering (CE)' : 'Computer Engineering (CpE)'; ?> - Course Prospectus</h4>
            <small id="semesterInfo">All Years - All Semesters</small>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered prospectus-table mb-0">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Descriptive Title</th>
                        <th class="text-center">Lec</th>
                        <th class="text-center">Lab</th>
                        <th class="text-center">Units</th>
                        <th>Year/Sem</th>
                        <th>Pre-requisite(s)</th>
                    </tr>
                </thead>
                <tbody id="prospectusTableBody">
                    <tr>
                        <td colspan="7" class="text-center">Loading prospectus...</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="table-secondary">
                        <td colspan="5" class="text-end fw-bold">Total:</td>
                        <td class="text-center fw-bold" id="totalUnits">0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    var course = '<?php echo $user['course']; ?>';
    
    // Auto-load when filters change
    $('#yearLevel, #semester').on('change', function() {
        loadProspectus();
    });
    
    // Load prospectus function
    function loadProspectus() {
        var yearLevel = $('#yearLevel').val();
        var semester = $('#semester').val();
        
        // Update the info text
        var yearText = yearLevel === 'all' ? 'All Years' : yearLevel + ' Year';
        var semText = semester === 'all' ? 'All Semesters' : (semester == 1 ? '1st Semester' : '2nd Semester');
        $('#semesterInfo').text(yearText + ' - ' + semText);
        
        // Show loading state
        $('#prospectusTableBody').html('<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>');
        
        $.ajax({
            url: '../api/get_prospectus.php',
            method: 'POST',
            data: { 
                year_level: yearLevel, 
                semester: semester, 
                course: course 
            },
            dataType: 'json',
            success: function(data) {
                var html = '';
                var totalUnits = 0;
                
                if(data.length > 0) {
                    $.each(data, function(index, subject) {
                        totalUnits += parseFloat(subject.units);
                        var yearSemDisplay = subject.year_level + ' Year - ' + (subject.semester == 1 ? '1st Sem' : '2nd Sem');
                        html += '<tr>';
                        html += '<td>' + subject.subject_code + '</td>';
                        html += '<td>' + subject.descriptive_title + '</td>';
                        html += '<td class="text-center">' + subject.lec + '</td>';
                        html += '<td class="text-center">' + subject.lab + '</td>';
                        html += '<td class="text-center">' + subject.units + '</td>';
                        html += '<td class="text-center">' + yearSemDisplay + '</td>';
                        html += '<td>' + (subject.prerequisite_text || '-') + '</td>';
                        html += '</tr>';
                    });
                } else {
                    html = '<tr><td colspan="7" class="text-center">No subjects found for the selected filters</td></tr>';
                }
                
                $('#prospectusTableBody').html(html);
                $('#totalUnits').text(totalUnits);
            },
            error: function() {
                $('#prospectusTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading prospectus. Please try again.</td></tr>');
            }
        });
    }
    
    // Load on page load
    loadProspectus();
});
</script>
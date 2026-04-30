<?php
require_once '../includes/auth.php';
requireRole('professor');

$user = getUserData($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prospectus - Professor View</title>
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
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }
        .course-tab {
            cursor: pointer;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s;
            text-align: center;
        }
        .course-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .course-tab:not(.active):hover {
            background: #e2e8f0;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }
        .program-info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .program-info-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
        }
        .stat-box {
            transition: all 0.3s;
        }
        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        @media print {
            .sidebar, .btn-print, .filter-section, .chatbot-icon, .navbar, .course-tabs, .no-print {
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

    <!-- Course Selection Tabs -->
    <div class="course-tabs mb-4">
        <div class="row g-2">
            <div class="col-md-3">
                <div class="course-tab active" data-course="CE">
                    <i class="fas fa-building me-2"></i>Civil Engineering (CE)
                </div>
            </div>
            <div class="col-md-3">
                <div class="course-tab" data-course="CpE">
                    <i class="fas fa-microchip me-2"></i>Computer Engineering (CpE)
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
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

    <!-- Prospectus Table -->
    <div class="prospectus-card" id="prospectusContent">
        <div class="prospectus-header">
            <h4 class="mb-0" id="courseTitle">Civil Engineering (CE) - Course Prospectus</h4>
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

    <!-- Program Information Card -->
    <div class="program-info-card">
        <div class="program-info-header">
            <h4 class="mb-0"><i class="fas fa-info-circle me-2"></i>Program Information</h4>
        </div>
        <div class="p-4" id="programInfoContent">
            <div class="row">
                <div class="col-12">
                    <h5 id="programTitle"><i class="fas fa-graduation-cap me-2 text-primary"></i>Civil Engineering (CE)</h5>
                    <p id="programDescription" class="text-muted">Loading...</p>
                    <hr>
                    <h6><i class="fas fa-briefcase me-2 text-primary"></i>Career Opportunities</h6>
                    <p id="programCareer" class="text-muted">Loading...</p>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <div class="stat-box text-center p-3 bg-light rounded">
                                <h3 class="text-primary mb-0" id="infoTotalSubjects">0</h3>
                                <small class="text-muted">Total Subjects</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box text-center p-3 bg-light rounded">
                                <h3 class="text-primary mb-0" id="infoTotalUnits">0</h3>
                                <small class="text-muted">Total Units</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    var currentCourse = 'CE';
    
    // Program information data
    var programInfo = {
        'CE': {
            title: 'Civil Engineering (CE)',
            description: 'The Bachelor of Science in Civil Engineering (BSCE) is a four-year program that provides students with knowledge and skills in the design, construction, and maintenance of infrastructure projects such as buildings, roads, bridges, and water supply systems.',
            career: 'Graduates may pursue careers as: Civil Engineer, Structural Engineer, Geotechnical Engineer, Transportation Engineer, Construction Manager, Water Resources Engineer, and Project Consultant.'
        },
        'CpE': {
            title: 'Computer Engineering (CpE)',
            description: 'The Bachelor of Science in Computer Engineering (BSCpE) is a four-year program that combines electrical engineering and computer science, focusing on the design and development of computer systems, hardware, software, and networks.',
            career: 'Graduates may pursue careers as: Computer Engineer, Hardware Engineer, Network Engineer, Embedded Systems Developer, Software Engineer, IT Consultant, and Systems Architect.'
        }
    };
    
    // Function to update program information display
    function updateProgramInfo() {
        var info = programInfo[currentCourse];
        $('#programTitle').html('<i class="fas fa-graduation-cap me-2 text-primary"></i>' + info.title);
        $('#programDescription').text(info.description);
        $('#programCareer').text(info.career);
    }
    
    // Course tab click handler
    $('.course-tab').click(function() {
        $('.course-tab').removeClass('active');
        $(this).addClass('active');
        currentCourse = $(this).data('course');
        
        var courseTitle = currentCourse === 'CE' ? 'Civil Engineering (CE)' : 'Computer Engineering (CpE)';
        $('#courseTitle').text(courseTitle + ' - Course Prospectus');
        
        // Update program info
        updateProgramInfo();
        
        // Reset totals to loading state
        $('#infoTotalSubjects').text('--');
        $('#infoTotalUnits').text('--');
        
        // Reload prospectus with new course
        loadProspectus();
    });
    
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
                course: currentCourse 
            },
            dataType: 'json',
            success: function(data) {
                var html = '';
                var totalUnits = 0;
                var totalSubjects = data.length;
                
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
                
                // Update program info totals based on filtered results
                $('#infoTotalSubjects').text(totalSubjects);
                $('#infoTotalUnits').text(totalUnits);
            },
            error: function() {
                $('#prospectusTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading prospectus. Please try again.</td></tr>');
            }
        });
    }
    
    // Initial load - Update program info and load prospectus
    updateProgramInfo();
    loadProspectus();
});
</script>
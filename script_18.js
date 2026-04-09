
        // ทำให้การ์ดรายงานคลิกได้ทั้งใบ
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.report-section[data-link], .dashboard-link-card[data-link]').forEach(function(card) {
                card.setAttribute('role', 'link');
                card.setAttribute('tabindex', '0');

                var navigate = function() {
                    var url = card.getAttribute('data-link');
                    if (url) window.location.href = url;
                };

                card.addEventListener('click', function(e) {
                    var interactive = e.target.closest('a, button, input, select, textarea, label');
                    if (interactive) return;
                    navigate();
                });

                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        navigate();
                    }
                });
            });
        });

        function initDashboardCharts() {
        // สีสำหรับ Charts
        const colors = {
            primary: 'rgba(0, 123, 255, 0.7)',
            primaryBorder: 'rgb(0, 123, 255)',
            success: 'rgba(40, 167, 69, 0.7)',
            successBorder: 'rgb(40, 167, 69)',
            danger: 'rgba(220, 53, 69, 0.7)',
            dangerBorder: 'rgb(220, 53, 69)',
            warning: 'rgba(255, 193, 7, 0.7)',
            warningBorder: 'rgb(255, 193, 7)',
            info: 'rgba(23, 162, 184, 0.7)',
            infoBorder: 'rgb(23, 162, 184)'
        };

        /* Chart: สถานะห้อง */
        const roomStatusCtx = document.getElementById('roomStatusChart').getContext('2d');
        new Chart(roomStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['ว่าง', 'ไม่ว่าง'],
                datasets: [{
                    data: [25, 5],
                    backgroundColor: [colors.success, colors.danger],
                    borderColor: [colors.successBorder, colors.dangerBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 14 } }
                    }
                }
            }
        });

        /* Chart: สถานะการแจ้งซ่อม */
        const repairStatusCtx = document.getElementById('repairStatusChart').getContext('2d');
        new Chart(repairStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอซ่อม', 'กำลังซ่อม', 'ซ่อมเสร็จ'],
                datasets: [{
                    data: [0, 0, 5],
                    backgroundColor: [colors.danger, colors.warning, colors.success],
                    borderColor: [colors.dangerBorder, colors.warningBorder, colors.successBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 14 } }
                    }
                }
            }
        });

        /* Chart: สถานะการชำระ */
        const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
        new Chart(paymentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอตรวจสอบ', 'ตรวจสอบแล้ว'],
                datasets: [{
                    data: [0, 68],
                    backgroundColor: [colors.warning, colors.success],
                    borderColor: [colors.warningBorder, colors.successBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 14 } }
                    }
                }
            }
        });

        /* Chart: รายได้รายเดือน */
        const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
        new Chart(monthlyRevenueCtx, {
            type: 'line',
            data: {
                labels: [
                    'มี.ค. 2569','เม.ย. 2569',                ],
                datasets: [{
                    label: 'รายได้ (บาท)',
                    data: [
                        19000,130142,                    ],
                    borderColor: colors.primaryBorder,
                    backgroundColor: colors.primary,
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: colors.primaryBorder,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { font: { size: 14 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '฿' + value.toLocaleString('th-TH');
                            }
                        }
                    }
                }
            }
        });

        // Chart: Booking Trend (7 days)
        const bookingTrendCtx = document.getElementById('bookingTrendChart').getContext('2d');
        new Chart(bookingTrendCtx, {
            type: 'bar',
            data: {
                labels: [
                    'ศ. 3 เม.ย.','ส. 4 เม.ย.','อา. 5 เม.ย.','จ. 6 เม.ย.','อ. 7 เม.ย.','พ. 8 เม.ย.','พฤ. 9 เม.ย.',                ],
                datasets: [{
                    label: 'Bookings',
                    data: [
                        5,0,3,2,6,2,2,                    ],
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderColor: 'rgb(99, 102, 241)',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Chart: Contract Status
        const contractStatusCtx = document.getElementById('contractStatusChart').getContext('2d');
        new Chart(contractStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Ended', 'Pending Cancel'],
                datasets: [{
                    data: [
                        12,
                        21,
                        1                    ],
                    backgroundColor: [colors.success, colors.danger, colors.warning],
                    borderColor: [colors.successBorder, colors.dangerBorder, colors.warningBorder],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 12 } }
                    }
                }
            }
        });

        // Chart: Payment Trend
        const paymentTrendCtx = document.getElementById('paymentTrendChart').getContext('2d');
        new Chart(paymentTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    'ศ. 3','ส. 4','อา. 5','จ. 6','อ. 7','พ. 8','พฤ. 9',                ],
                datasets: [{
                    label: 'Payments (฿)',
                    data: [
                        20108,0,51229,5500,37605,8700,7000,                    ],
                    borderColor: colors.primaryBorder,
                    backgroundColor: 'rgba(217, 70, 239, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: colors.primaryBorder,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '฿' + (value / 1000).toFixed(1) + 'k';
                            }
                        }
                    }
                }
            }
        });

        // Chart: Repair Status Distribution
        const repairDistributionCtx = document.getElementById('repairDistributionChart').getContext('2d');
        new Chart(repairDistributionCtx, {
            type: 'bar',
            data: {
                labels: ['Waiting', 'Processing', 'Completed'],
                datasets: [{
                    label: 'Repairs',
                    data: [
                        0,
                        0,
                        5                    ],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(251, 146, 60, 0.7)',
                        'rgba(34, 197, 94, 0.7)'
                    ],
                    borderColor: [
                        'rgb(239, 68, 68)',
                        'rgb(251, 146, 60)',
                        'rgb(34, 197, 94)'
                    ],
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Chart: Room Types Distribution
        const roomTypesCtx = document.getElementById('roomTypesChart').getContext('2d');
        new Chart(roomTypesCtx, {
            type: 'polarArea',
            data: {
                labels: [
                    'ฝั่งเก่า','ฝั่งใหม่',                ],
                datasets: [{
                    data: [
                        17,13,                    ],
                    backgroundColor: [
                        'rgba(244, 63, 94, 0.7)',
                        'rgba(168, 85, 247, 0.7)',
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(20, 184, 166, 0.7)',
                        'rgba(132, 204, 22, 0.7)',
                        'rgba(251, 146, 60, 0.7)'
                    ],
                    borderColor: [
                        'rgb(244, 63, 94)',
                        'rgb(168, 85, 247)',
                        'rgb(59, 130, 246)',
                        'rgb(20, 184, 166)',
                        'rgb(132, 204, 22)',
                        'rgb(251, 146, 60)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { font: { size: 11 }, padding: 12 }
                    }
                }
            }
        });

        // Chart: Utility Usage Trend
        const utilityTrendCtx = document.getElementById('utilityTrendChart').getContext('2d');
        new Chart(utilityTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    'มี.ค. 2569','เม.ย. 2569',                ],
                datasets: [
                    {
                        label: 'น้ำ (ยูนิต)',
                        data: [0,1.4,],
                        borderColor: 'rgb(14, 165, 233)',
                        backgroundColor: 'rgba(14, 165, 233, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgb(14, 165, 233)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'ไฟ (ยูนิต)',
                        data: [0,135.2,],
                        borderColor: 'rgb(245, 158, 11)',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgb(245, 158, 11)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { size: 12 }, usePointStyle: true }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });

        // Chart: Tenant Check-in Trend
        const tenantCheckinCtx = document.getElementById('tenantCheckinChart').getContext('2d');
        new Chart(tenantCheckinCtx, {
            type: 'bar',
            data: {
                labels: [
                    'ศ. 3','ส. 4','อา. 5','จ. 6','อ. 7','พ. 8','พฤ. 9',                ],
                datasets: [{
                    label: 'ผู้เช่าเข้าใหม่',
                    data: [
                        5,0,3,2,7,2,2,                    ],
                    backgroundColor: 'rgba(132, 204, 22, 0.7)',
                    borderColor: 'rgb(132, 204, 22)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // ===== MINI CHARTS FOR REPORT SECTIONS =====
        
        // Mini Chart: Booking Status
        const miniBookingCtx = document.getElementById('miniBookingChart').getContext('2d');
        new Chart(miniBookingCtx, {
            type: 'doughnut',
            data: {
                labels: ['เข้าพัก', 'จองอยู่'],
                datasets: [{
                    data: [12, 0],
                    backgroundColor: ['rgba(59, 130, 246, 0.8)', 'rgba(147, 197, 253, 0.8)'],
                    borderColor: ['rgb(59, 130, 246)', 'rgb(147, 197, 253)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: News (Bar showing count)
        const miniNewsCtx = document.getElementById('miniNewsChart').getContext('2d');
        new Chart(miniNewsCtx, {
            type: 'bar',
            data: {
                labels: ['ข่าว'],
                datasets: [{
                    data: [5],
                    backgroundColor: 'rgba(6, 182, 212, 0.7)',
                    borderColor: 'rgb(6, 182, 212)',
                    borderWidth: 2,
                    borderRadius: 8,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { display: false } }
            }
        });

        // Mini Chart: Repair Status
        const miniRepairCtx = document.getElementById('miniRepairChart').getContext('2d');
        new Chart(miniRepairCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอซ่อม', 'กำลังซ่อม', 'เสร็จแล้ว'],
                datasets: [{
                    data: [0, 0, 5],
                    backgroundColor: ['rgba(239, 68, 68, 0.8)', 'rgba(245, 158, 11, 0.8)', 'rgba(34, 197, 94, 0.8)'],
                    borderColor: ['rgb(239, 68, 68)', 'rgb(245, 158, 11)', 'rgb(34, 197, 94)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: Invoice Status
        const miniInvoiceCtx = document.getElementById('miniInvoiceChart').getContext('2d');
        new Chart(miniInvoiceCtx, {
            type: 'doughnut',
            data: {
                labels: ['รอตรวจสอบ', 'ตรวจสอบแล้ว'],
                datasets: [{
                    data: [0, 68],
                    backgroundColor: ['rgba(245, 158, 11, 0.8)', 'rgba(34, 197, 94, 0.8)'],
                    borderColor: ['rgb(245, 158, 11)', 'rgb(34, 197, 94)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: Payment Amount (Gauge-like)
        const miniPaymentCtx = document.getElementById('miniPaymentChart').getContext('2d');
        new Chart(miniPaymentCtx, {
            type: 'doughnut',
            data: {
                labels: ['ยอดชำระ', ''],
                datasets: [{
                    data: [149142, 0],
                    backgroundColor: ['rgba(34, 197, 94, 0.8)', 'rgba(229, 231, 235, 0.3)'],
                    borderColor: ['rgb(34, 197, 94)', 'transparent'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                rotation: -90,
                circumference: 180,
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: Room Status
        const miniRoomCtx = document.getElementById('miniRoomChart').getContext('2d');
        new Chart(miniRoomCtx, {
            type: 'doughnut',
            data: {
                labels: ['ว่าง', 'ไม่ว่าง'],
                datasets: [{
                    data: [25, 5],
                    backgroundColor: ['rgba(34, 197, 94, 0.8)', 'rgba(239, 68, 68, 0.8)'],
                    borderColor: ['rgb(34, 197, 94)', 'rgb(239, 68, 68)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        // Mini Chart: Utility Comparison
        const miniUtilityCtx = document.getElementById('miniUtilityChart').getContext('2d');
        new Chart(miniUtilityCtx, {
            type: 'bar',
            data: {
                labels: ['น้ำ', 'ไฟ'],
                datasets: [{
                    data: [5.63, 540.75],
                    backgroundColor: ['rgba(14, 165, 233, 0.8)', 'rgba(245, 158, 11, 0.8)'],
                    borderColor: ['rgb(14, 165, 233)', 'rgb(245, 158, 11)'],
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { display: false, beginAtZero: true }, 
                    x: { grid: { display: false } } 
                }
            }
        });

        // Mini Chart: Revenue (Bar chart - works better with few data points)
        const miniRevenueCtx = document.getElementById('miniRevenueChart');
        if (miniRevenueCtx) {
            const revenueLabelsRaw = ["มี.ค. 2569","เม.ย. 2569"];
            const revenueDataRaw = [19000,130142];
            const revenueLabels = revenueLabelsRaw.length ? revenueLabelsRaw : ['ไม่พบข้อมูล'];
            const revenueData = revenueDataRaw.length ? revenueDataRaw : [0];
            
            new Chart(miniRevenueCtx.getContext('2d'), {
                type: revenueData.length <= 2 ? 'bar' : 'line',
                data: {
                    labels: revenueLabels,
                    datasets: [{
                        data: revenueData,
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: revenueData.length <= 2 ? 'rgba(99, 102, 241, 0.7)' : 'rgba(99, 102, 241, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: revenueData.length <= 3 ? 4 : 0,
                        pointBackgroundColor: 'rgb(99, 102, 241)',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { display: false, beginAtZero: true }, 
                        x: { 
                            display: revenueData.length <= 3,
                            grid: { display: false },
                            ticks: { 
                                font: { size: 10 },
                                color: 'rgba(148, 163, 184, 0.8)'
                            }
                        } 
                    }
                }
            });
        }

        // Mini Chart: Contract Status
        const miniContractCtx = document.getElementById('miniContractChart').getContext('2d');
        new Chart(miniContractCtx, {
            type: 'doughnut',
            data: {
                labels: ['ใช้งาน', 'สิ้นสุด'],
                datasets: [{
                    data: [12, 21],
                    backgroundColor: ['rgba(236, 72, 153, 0.8)', 'rgba(156, 163, 175, 0.5)'],
                    borderColor: ['rgb(236, 72, 153)', 'rgb(156, 163, 175)'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });
        }

        (function bootstrapDashboardCharts() {
            if (typeof window.Chart !== 'undefined') {
                initDashboardCharts();
                return;
            }

            var fallbackScript = document.createElement('script');
            fallbackScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js';
            fallbackScript.onload = initDashboardCharts;
            fallbackScript.onerror = function() {
                console.error('[Dashboard] Unable to load Chart.js from CDN fallback');
            };
            document.head.appendChild(fallbackScript);
        })();
    
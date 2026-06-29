import Chart from 'chart.js/auto';

const readDashboardData = () => {
    const payload = document.getElementById('dashboard-chart-data');

    if (!payload) {
        return null;
    }

    try {
        return JSON.parse(payload.textContent || '{}');
    } catch (error) {
        console.warn('Dashboard chart data tidak valid.', error);

        return null;
    }
};

const makeBarChart = (canvas, dataset) => {
    if (!canvas || !dataset) {
        return;
    }

    const chartDatasets = dataset.datasets || [
        {
            data: dataset.data,
            backgroundColor: dataset.colors,
            borderRadius: 8,
            maxBarThickness: 48,
        },
    ];

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: dataset.labels,
            datasets: chartDatasets.map((chartDataset) => ({
                ...chartDataset,
                borderRadius: 8,
                maxBarThickness: 48,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: Boolean(dataset.datasets),
                    labels: {
                        boxWidth: 10,
                        boxHeight: 10,
                        color: '#475569',
                        font: {
                            weight: 600,
                        },
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const label = context.dataset.label ? `${context.dataset.label}: ` : '';

                            return `${label}${context.parsed.y} surat`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    stacked: Boolean(dataset.datasets),
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: '#475569',
                        font: {
                            weight: 600,
                        },
                    },
                },
                y: {
                    stacked: Boolean(dataset.datasets),
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: '#64748b',
                    },
                    grid: {
                        color: '#e2e8f0',
                    },
                },
            },
        },
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const dashboardData = readDashboardData();

    if (!dashboardData) {
        return;
    }

    makeBarChart(document.getElementById('suratPerJenisChart'), dashboardData.suratPerJenis);
});

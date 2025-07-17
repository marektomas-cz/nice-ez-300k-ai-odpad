import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export class MetricsDashboard {
    constructor() {
        this.charts = {};
        this.websocket = null;
        this.metricsData = {
            executions: [],
            errors: [],
            performance: [],
            resources: []
        };
    }

    static init() {
        return new MetricsDashboard().initialize();
    }

    initialize() {
        this.setupWebSocket();
        this.createCharts();
        this.loadInitialData();
        this.startPeriodicUpdates();
    }

    setupWebSocket() {
        if (!window.WebSocket) return;

        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws/metrics`;
        
        this.websocket = new WebSocket(wsUrl);
        
        this.websocket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.updateMetrics(data);
        };
        
        this.websocket.onclose = () => {
            // Reconnect after 5 seconds
            setTimeout(() => this.setupWebSocket(), 5000);
        };
    }

    createCharts() {
        this.createExecutionChart();
        this.createErrorChart();
        this.createPerformanceChart();
        this.createResourceChart();
    }

    createExecutionChart() {
        const ctx = document.getElementById('execution-chart');
        if (!ctx) return;

        this.charts.execution = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Script Executions',
                    data: [],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Successful Executions',
                    data: [],
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Script Execution Metrics'
                    },
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    createErrorChart() {
        const ctx = document.getElementById('error-chart');
        if (!ctx) return;

        this.charts.error = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Script Errors',
                    data: [],
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgb(255, 99, 132)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Error Distribution'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    createPerformanceChart() {
        const ctx = document.getElementById('performance-chart');
        if (!ctx) return;

        this.charts.performance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Average Execution Time (ms)',
                    data: [],
                    borderColor: 'rgb(255, 206, 86)',
                    backgroundColor: 'rgba(255, 206, 86, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Memory Usage (MB)',
                    data: [],
                    borderColor: 'rgb(153, 102, 255)',
                    backgroundColor: 'rgba(153, 102, 255, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Performance Metrics'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Time (ms)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Memory (MB)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }

    createResourceChart() {
        const ctx = document.getElementById('resource-chart');
        if (!ctx) return;

        this.charts.resource = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['CPU Usage', 'Memory Usage', 'Database Queries', 'HTTP Requests'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)'
                    ],
                    borderColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 206, 86)',
                        'rgb(75, 192, 192)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Resource Usage Distribution'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    loadInitialData() {
        fetch('/api/metrics/dashboard')
            .then(response => response.json())
            .then(data => {
                this.metricsData = data;
                this.updateAllCharts();
            })
            .catch(error => {
                console.error('Error loading metrics:', error);
            });
    }

    updateMetrics(newData) {
        // Update metrics data
        Object.keys(newData).forEach(key => {
            if (this.metricsData[key]) {
                this.metricsData[key] = [...this.metricsData[key], ...newData[key]];
                // Keep only last 50 data points
                if (this.metricsData[key].length > 50) {
                    this.metricsData[key] = this.metricsData[key].slice(-50);
                }
            }
        });

        this.updateAllCharts();
    }

    updateAllCharts() {
        this.updateExecutionChart();
        this.updateErrorChart();
        this.updatePerformanceChart();
        this.updateResourceChart();
    }

    updateExecutionChart() {
        if (!this.charts.execution) return;

        const chart = this.charts.execution;
        const data = this.metricsData.executions;

        chart.data.labels = data.map(item => item.timestamp);
        chart.data.datasets[0].data = data.map(item => item.total);
        chart.data.datasets[1].data = data.map(item => item.successful);

        chart.update('none');
    }

    updateErrorChart() {
        if (!this.charts.error) return;

        const chart = this.charts.error;
        const data = this.metricsData.errors;

        // Group errors by type
        const errorTypes = {};
        data.forEach(error => {
            errorTypes[error.type] = (errorTypes[error.type] || 0) + 1;
        });

        chart.data.labels = Object.keys(errorTypes);
        chart.data.datasets[0].data = Object.values(errorTypes);

        chart.update('none');
    }

    updatePerformanceChart() {
        if (!this.charts.performance) return;

        const chart = this.charts.performance;
        const data = this.metricsData.performance;

        chart.data.labels = data.map(item => item.timestamp);
        chart.data.datasets[0].data = data.map(item => item.avg_time);
        chart.data.datasets[1].data = data.map(item => item.memory_usage);

        chart.update('none');
    }

    updateResourceChart() {
        if (!this.charts.resource) return;

        const chart = this.charts.resource;
        const data = this.metricsData.resources;

        if (data.length > 0) {
            const latest = data[data.length - 1];
            chart.data.datasets[0].data = [
                latest.cpu_usage,
                latest.memory_usage,
                latest.db_queries,
                latest.http_requests
            ];
        }

        chart.update('none');
    }

    startPeriodicUpdates() {
        // Update every 30 seconds if no WebSocket
        if (!this.websocket) {
            setInterval(() => {
                this.loadInitialData();
            }, 30000);
        }
    }
}
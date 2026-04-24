import type { EChartsOption } from 'echarts';
import {
    BarChart,
    LineChart,
    ScatterChart,
} from 'echarts/charts';
import {
    GridComponent,
    LegendComponent,
    MarkLineComponent,
    TooltipComponent,
} from 'echarts/components';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';

use([
    BarChart,
    LineChart,
    ScatterChart,
    GridComponent,
    LegendComponent,
    MarkLineComponent,
    TooltipComponent,
    CanvasRenderer,
]);

export type DashboardEChartsOption = EChartsOption;

export const dashboardChartPalette = {
    production: '#2563eb',
    utilization: '#d97706',
    backlog: '#2563eb',
    intake: '#0d9488',
    completed: '#d97706',
    balance: '#059669',
    capacity: '#dc2626',
    warning: '#d97706',
    critical: '#dc2626',
    axisLabel: '#64748b',
    axisLine: '#cbd5e1',
    gridLine: '#e2e8f0',
    tooltipBg: 'rgba(15, 23, 42, 0.94)',
    tooltipText: '#f8fafc',
} as const;

export const dashboardChartPreset: DashboardEChartsOption = {
    animationDuration: 450,
    animationDurationUpdate: 320,
    textStyle: {
        fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
        fontSize: 12,
        fontWeight: 500,
    },
    grid: {
        top: 26,
        right: 48,
        bottom: 72,
        left: 52,
        containLabel: false,
    },
    tooltip: {
        trigger: 'axis',
        axisPointer: {
            type: 'line',
            lineStyle: {
                color: '#94a3b8',
                width: 1,
                type: 'dashed',
            },
        },
        backgroundColor: dashboardChartPalette.tooltipBg,
        borderWidth: 0,
        textStyle: {
            color: dashboardChartPalette.tooltipText,
            fontSize: 12,
            lineHeight: 18,
        },
        extraCssText: 'border-radius:10px;padding:10px 12px;',
    },
    xAxis: {
        axisLabel: {
            color: dashboardChartPalette.axisLabel,
            fontSize: 12,
            margin: 12,
            fontWeight: 600,
        },
        axisLine: {
            lineStyle: {
                color: dashboardChartPalette.axisLine,
                width: 1,
            },
        },
        axisTick: {
            show: false,
        },
    },
    yAxis: [
        {
            axisLabel: {
                color: dashboardChartPalette.axisLabel,
                fontSize: 12,
                fontWeight: 500,
            },
            splitLine: {
                lineStyle: {
                    color: dashboardChartPalette.gridLine,
                    type: 'dashed',
                    width: 1,
                },
            },
            axisLine: {
                show: false,
            },
            axisTick: {
                show: false,
            },
        },
    ],
};

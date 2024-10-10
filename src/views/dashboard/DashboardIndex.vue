<template>
	<NcAppContent>
		<h2 class="pageHeader">
			Dashboard
		</h2>

		<div class="dashboard-content">
			<div class="graphs">
				<div>
					<h5>Source calls</h5>
					<div class="content">
						<apexchart
							width="500"
							:options="sourcesCalls.options"
							:series="sourcesCalls.series" />
					</div>
				</div>
				<div>
					<h5>Zoek aanvragen per uur</h5>
					<div class="content">
						<apexchart
							width="500"
							:options="incomingCalls.options"
							:series="incomingCalls.series" />
					</div>
				</div>
			</div>
		</div>
	</NcAppContent>
</template>

<script>

import { NcAppContent } from '@nextcloud/vue'
import VueApexCharts from 'vue-apexcharts'

import { getTheme } from '../../services/getTheme.js'

export default {
	name: 'DashboardIndex',
	components: {
		NcAppContent,
		apexchart: VueApexCharts,
	},
	data() {
		return {
			// mock data
			sourcesCalls: {
				options: {
					theme: {
						mode: getTheme(),
						monochrome: {
							enabled: true,
							color: '#079cff',
							shadeTo: 'light',
							shadeIntensity: 0,
						},
					},
					chart: {
						id: 'source-calls',
						type: 'area',
					},
					dataLabels: {
						enabled: false,
					},
					stroke: {
						curve: 'smooth',
					},
					xaxis: {
						categories: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15',
							'16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30'],
					},
				},
				series: [{
					name: 'outgoing calls',
					data: [
						432, 2954, 3287, 3278, 5893, 6839, 7751, 5223, 4332, 4343,
						4324, 2234, 4902, 4321, 5325, 5324, 3211, 3223, 4432, 2243,
						4123, 5344, 3754, 4243, 5233, 6435, 4234, 6454, 4344, 3372, // 30 total data points
					],
				}],
			},
			incomingCalls: {
				options: {
					theme: {
						mode: getTheme(),
						monochrome: {
							enabled: true,
							color: '#079cff',
							shadeTo: 'light',
							shadeIntensity: 0,
						},
					},
					chart: {
						id: 'Queries-per-uur',
						type: 'area',
					},
					dataLabels: {
						enabled: false,
					},
					stroke: {
						curve: 'smooth',
					},
					xaxis: {
						categories: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12',
							'13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24'],
					},
				},
				series: [{
					name: 'incomming calls',
					data: [
						32, 43, 12, 23, 32, 45, 32, 12, 31, 42,
						43, 64, 34, 32, 32, 23, 25, 13, 35, 24,
						35, 43, 23, 42, // 24 total data points
					],
				}],
			},

			month: {
				options: {
					theme: {
						mode: getTheme(),
						monochrome: {
							enabled: true,
							color: '#079cff',
							shadeTo: 'light',
							shadeIntensity: 0,
						},
					},
					chart: {
						id: 'Clicks-per-dag',
						type: 'area',
					},
					dataLabels: {
						enabled: false,
					},
					stroke: {
						curve: 'smooth',
					},
					xaxis: {
						categories: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15',
							'16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30'],
					},
				},
				series: [{
					name: 'Detail bevragingen per dag',
					data: [
						51434, 32559, 32264, 39290, 32854, 54697, 37360, 56957, 34450, 55016,
						34219, 30221, 23969, 59032, 53820, 39792, 52498, 22735, 42846, 50663,
						22112, 20460, 51664, 33078, 41214, 42888, 26108, 58835, 40915, 25714,
					],
				}],
			},
		}
	},
}
</script>

<style>
.apexcharts-svg {
    background-color: transparent !important;
}

.dashboard-content {
    margin-inline: auto;
    max-width: 1000px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}
.dashboard-content > * {
    margin-block-end: 4rem;
}

/* default theme */
@media (prefers-color-scheme: light) {
    :root {
        --dashboard-item-background-color: rgba(0, 0, 0, 0.07);
    }
}
@media (prefers-color-scheme: dark) {
    :root {
        --dashboard-item-background-color: rgba(255, 255, 255, 0.1);
    }
}
/* do theme checks, light mode | dark mode */
:root:has(body[data-theme-light]) {
    --dashboard-item-background-color: rgba(0, 0, 0, 0.07);
}
:root:has(body[data-theme-dark]) {
    --dashboard-item-background-color: rgba(255, 255, 255, 0.1);
}

/* graphs */
.dashboard-content > .graphs {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
    gap: 2rem;
}
.dashboard-content > .graphs .full-width {
    grid-column-start: 1;
    grid-column-end: 3;
}
.dashboard-content > .graphs .content {
    display: flex;
    gap: 4px;
}
.dashboard-content > .graphs .dashboard-small-list {
    display: flex;
    flex-direction: column;
}
.dashboard-content > .graphs .dashboard-small-list > li {
    padding: 1rem;
}
.dashboard-content > .graphs .dashboard-small-list > li:not(:last-child) {
    border-bottom: 1px solid grey;
}
</style>

<template>
	<div>
		<DeleteSource />
		<EditSource />
		<TestSource />
		<DeleteJob />
		<TestJob />
		<EditJob />
		<DeleteLog />
		<EditLog />
		<DeleteMapping />
		<EditMapping />
		<EditMappingMapping />
		<DeleteMappingMapping />
		<EditMappingCast />
		<DeleteMappingCast />
		<DeleteSynchronization />
		<EditSynchronization />
		<EditJobArgument />
		<DeleteJobArgument />
		<EditSourceConfiguration />
		<DeleteSourceConfiguration />
		<ViewSourceLog />
		<ViewJobLog />
	</div>
</template>

<script>
import { navigationStore } from '../store/store.js'
import EditSource from './Source/EditSource.vue'
import TestSource from './TestSource/TestSource.vue'
import DeleteSource from './Source/DeleteSource.vue'
import TestJob from './Job/TestJob.vue'
import EditJob from './Job/EditJob.vue'
import DeleteJob from './Job/DeleteJob.vue'
import EditLog from './Log/EditLog.vue'
import DeleteLog from './Log/DeleteLog.vue'
import EditMapping from './Mapping/EditMapping.vue'
import DeleteMapping from './Mapping/DeleteMapping.vue'
import EditSynchronization from './Synchronization/EditSynchronization.vue'
import DeleteSynchronization from './Synchronization/DeleteSynchronization.vue'
import EditJobArgument from './JobArgument/EditJobArgument.vue'
import DeleteJobArgument from './JobArgument/DeleteJobArgument.vue'
import EditSourceConfiguration from './SourceConfiguration/EditSourceConfiguration.vue'
import DeleteSourceConfiguration from './SourceConfiguration/DeleteSourceConfiguration.vue'
import ViewSourceLog from './Log/ViewSourceLog.vue'
import EditMappingMapping from './mappingMapping/EditMappingMapping.vue'
import DeleteMappingMapping from './mappingMapping/DeleteMappingMapping.vue'
import EditMappingCast from './mappingCast/EditMappingCast.vue'
import DeleteMappingCast from './mappingCast/DeleteMappingCast.vue'
import ViewJobLog from './Log/ViewJobLog.vue'

export default {
	name: 'Modals',
	components: {
		DeleteSource,
		EditSource,
		TestSource,
		DeleteJob,
		EditJob,
		TestJob,
		DeleteLog,
		EditLog,
		DeleteMapping,
		EditMapping,
		DeleteSynchronization,
		EditSynchronization,
		EditJobArgument,
		DeleteJobArgument,
		EditSourceConfiguration,
		DeleteSourceConfiguration,
		ViewSourceLog,
		EditMappingMapping,
		DeleteMappingMapping,
		EditMappingCast,
		DeleteMappingCast,
		ViewJobLog,
	},
	setup() {
		return {
			navigationStore,
		}
	},
}
</script>

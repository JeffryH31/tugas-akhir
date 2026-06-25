<script setup>
const props = defineProps({
  modelValue: { type: String, default: "" },
  canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save"]);

const onBlur = () => {
  emit("save");
};
</script>

<template>
  <div class="section-card">
    <div class="d-flex align-center ga-2 pa-3 pb-2">
      <v-icon size="18" color="grey">mdi-text</v-icon>
      <span class="text-body-2 font-weight-medium">Description</span>
    </div>
    <div class="px-3 pb-3">
      <v-textarea v-if="canEdit" :model-value="modelValue" placeholder="Add a description..." variant="outlined"
        rows="3" hide-details auto-grow @update:model-value="emit('update:modelValue', $event)" @blur="onBlur" />
      <div v-else class="description-readonly text-body-2">
        {{ modelValue || 'No description' }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.section-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
  overflow: hidden;
}

.description-readonly {
  color: rgba(255, 255, 255, 0.7);
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.6;
  min-height: 40px;
  padding: 8px 0;
}
</style>

<template>
    <div class="list-group__tags">
        <el-tag v-for="tag in tags" type="info" :key="tag.id"><span class="badge badge-secondary">{{ tag }}</span>&nbsp;</el-tag>
    </div>
</template>

<script>
  export default {
    props: ['tags'],
    methods: {}
  }
</script>

<style scoped>
</style>

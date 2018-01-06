<template>
    <el-container>
        <el-main>
            <articles :mode="'popular'"><h3 slot="title">Die beliebtesten Einträge</h3></articles>
            <articles :mode="'latest'"><h3 slot="title">Die neuesten Einträge</h3></articles>
            <articles :mode="'modified'"><h3 slot="title">Die aktuellsten Einträge</h3></articles>
        </el-main>
        <el-aside></el-aside>
    </el-container>
</template>

<script>
  export default {
    name: 'StartPage',
  }
</script>

<style scoped>

</style>

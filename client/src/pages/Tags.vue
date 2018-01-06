<template>
    <el-container>
        <el-main>
            <h1>Tags</h1>
            <div v-loading="loading">
                <p class="text-small" v-if="tags.length>0">Zeige {{ tags.length }} Tags</p>
                <div class="list-group">
                    <a v-for="tag in tags" @click="toArticles(tag.name, $event)" href="#" class="list-group__item" :key="tag.id">
                        <h4>{{ tag.name}}</h4>
                        <span class="badge badge-secondary badge-pill">{{ tag.frequency }}</span>
                    </a>
                </div>
            </div>
        </el-main>
        <el-aside>
            <div class="articles-sort">
                <h4 class="articles-sort__title">Sortieren nach</h4>
                <div class="articles-sort__radios">
                    <div><el-radio v-model="sort" label="name" @change="loadData">Titel</el-radio></div>
                    <div><el-radio v-model="sort" label="frequency" @change="loadData">Beliebtheit</el-radio></div>
                    <div><el-radio v-model="sort" label="changed" @change="loadData">Letzter Änderung</el-radio></div>
                    <div><el-radio v-model="sort" label="created" @change="loadData">Letzter Eintrag</el-radio></div>
                </div>
            </div>
        </el-aside>
    </el-container>
</template>

<script>
  import { getTags } from '../utils/api'

  export default {
    data () {
      return {
        tags: [],
        sort: this.getSort(),
        loading: false
      }
    },
    methods: {
      getSort: function () {
        if (sessionStorage.getItem('TagsPage.sort')) {
          return sessionStorage.getItem('TagsPage.sort')
        }
        return 'frequency'
      },
      loadData: function () {
        this.loading = true
        var params = {
          sort: this.sort
        }
        getTags(params)
          .then((tags) => {
            this.tags = tags
            this.loading = false
            sessionStorage.setItem('TagsPage.sort', this.sort)
          })
          .catch(error => {
            console.error(error)
          })
      },
      toArticles: function (tag, e) {
        e.preventDefault()
        sessionStorage.setObj('ArticlesPage.selectedTags', [tag])
        this.$router.push('/articles')
      }
    },
    created: function () {
      this.loadData()
    }
  }

</script>

<style scoped>
    .list-group a {
        display: block;
    }
</style>

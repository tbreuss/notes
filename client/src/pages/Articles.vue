<template>
    <el-container>
        <el-main>
            <el-input class="articles-search" @change="loadData(true)" prefix-icon="el-icon-search" v-model="q" placeholder="Suchwort eingeben"></el-input>
            <div v-loading="loading">
                <h1>Einträge</h1>
                <p class="text-small" v-if="articles.length>0">
                    Zeige {{ pagingFrom }} bis {{ pagingTo }} von {{ paging.totalItems }} Einträgen
                </p>
                <p v-if="!loading && articles.length==0">Keine Einträge gefunden</p>
                <div class="list-group">
                    <router-link v-for="article in articles" :to="'/articles/' + article.id"
                                 class="list-group__item list-group-item-action" :key="article.id">
                        <h4>{{ article.title }}</h4>
                        <article-tags :tags="article.tags"></article-tags>
                    </router-link>
                </div>
                <nav style="margin-top:20px" v-if="paging.pageCount>1">
                    <ul class="pagination justify-content-center">
                        <li v-bind:class="{ disabled: paging.currentPage <= 1, 'page-item': true}"><a
                                class="page-link" @click="loadPrevPage" href="#">Vorherige Seite</a></li>
                        <li v-bind:class="{ disabled: paging.currentPage >= paging.pageCount, 'page-item': true}">
                            <a class="page-link" @click="loadNextPage" href="#">Nächste Seite</a></li>
                    </ul>
                </nav>
            </div>
        </el-main>
        <el-aside>
            <div class="articles-sort">
                <h4 class="articles-sort__title">Sortieren nach</h4>
                <div class="articles-sort__radios">
                    <div><el-radio v-model="sort" label="title" @change="loadData">Titel</el-radio></div>
                    <div><el-radio v-model="sort" label="popular" @change="loadData">Beliebtheit</el-radio></div>
                    <div><el-radio v-model="sort" label="changed" @change="loadData">Letzter Änderung</el-radio></div>
                    <div><el-radio v-model="sort" label="created" @change="loadData">Letzter Eintrag</el-radio></div>
                </div>
            </div>
            <div class="articles-tags">
                <h4 class="articles-tags__title">Filtern nach</h4>
                <el-checkbox-group v-model="selectedTags" class="articles-tags__checkboxes">
                    <div v-for="(tag, index) in tags">
                        <el-checkbox :label="tag" @change="loadData"></el-checkbox>
                    </div>
                </el-checkbox-group>
            </div>
        </el-aside>
    </el-container>
</template>

<script>

  import { getArticles, getSelectedTags } from '../utils/api'

  export default {
    name: 'ArticlesPage',
    data () {
      return {
        loading: false,
        articles: [],
        tags: [],
        q: this.getQ(),
        sort: this.getSort(),
        page: this.getPage(),
        paging: {
          currentPage: 1,
          itemsPerPage: 20,
          totalItems: 0
        },
        selectedTags: this.getSelectedTags(),
      }
    },
    computed: {
      pagingFrom: function () {
        return (this.paging.currentPage - 1) * this.paging.itemsPerPage + 1
      },
      pagingTo: function () {
        return Math.min(this.paging.currentPage * this.paging.itemsPerPage, this.paging.totalItems)
      }
    },
    methods: {
      loadData: function (resetPage = false) {
        var params = {}
        if (this.q) {
          params.q = this.q
        }
        if (this.selectedTags) {
          params.tags = this.selectedTags
        }
        if (this.sort) {
          params.sort = this.sort
        }
        if (resetPage) {
          this.page = 1
        }
        params.page = this.page
        this.loading = true
        getArticles(params)
          .then(data => {
            this.articles = data.articles
            this.paging = data.paging
            this.loading = false
            sessionStorage.setItem('ArticlesPage.page', this.page)
            sessionStorage.setItem('ArticlesPage.q', this.q)
            sessionStorage.setItem('ArticlesPage.sort', this.sort)
            this.loadTags()
          })
      },
      loadPrevPage: function (event) {
        event.preventDefault()
        this.page = this.paging.currentPage - 1
        this.loadData()
      },
      loadNextPage: function (event) {
        event.preventDefault()
        this.page = this.paging.currentPage + 1
        this.loadData()
      },
      loadTags: function () {
        var params = {}
        if (this.q) {
          params.q = this.q
        }
        if (this.selectedTags) {
          params.tags = this.selectedTags
        }
        getSelectedTags(params)
          .then((tags) => {
            this.tags = tags
            sessionStorage.setObj('ArticlesPage.selectedTags', this.selectedTags)
          })
          .catch(error => {
            console.error(error)
          })
      },
      getPage: function () {
        if (sessionStorage.getItem('ArticlesPage.page')) {
          return sessionStorage.getItem('ArticlesPage.page')
        }
        return 1
      },
      getQ: function () {
        if (sessionStorage.getItem('ArticlesPage.q')) {
          return sessionStorage.getItem('ArticlesPage.q')
        }
        return ''
      },
      getSort: function () {
        if (sessionStorage.getItem('ArticlesPage.sort')) {
          return sessionStorage.getItem('ArticlesPage.sort')
        }
        return 'title'
      },
      getSelectedTags: function () {
        if (sessionStorage.getObj('ArticlesPage.selectedTags')) {
          return sessionStorage.getObj('ArticlesPage.selectedTags')
        }
        return []
      }
    },
    created: function () {
      this.loadData()
    }
  }

</script>

<style scoped>

</style>

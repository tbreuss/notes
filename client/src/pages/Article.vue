<template>
    <el-container>
        <el-main>
            <div class="loading" v-if="loading">
                Lade...
            </div>
            <div v-else>

                <h1>{{ article.title }}</h1>

                <div class="content">
                    <vue-markdown :postrender="markdownPostRender" @rendered="markdownRendered">{{ article.content }}
                    </vue-markdown>
                </div>

                <hr>

                <div class="tags">
                    <h4>Tags</h4>
                    <article-tags :tags="article.tags"></article-tags>
                </div>

                <el-dialog title="Tips" :visible.sync="dialogVisible" width="30%">
                    <span>Soll der Eintrag gelöscht werden?</span>
                    <span slot="footer" class="dialog-footer">
                        <el-button @click="dialogVisible = false">Abbrechen</el-button>
                        <el-button type="primary" @click="deleteArticle">Löschen</el-button>
                    </span>
                </el-dialog>

                <div v-if="loggedIn" class="article-actions">
                    <h4>Aktionen</h4>
                    <el-button size="medium" type="primary" @click="$router.push('/articles/' + article.id + '/update')">Eintrag bearbeiten</el-button>
                    <el-button size="medium" type="danger" @click="dialogVisible = true">Eintrag löschen</el-button>
                </div>

            </div>
        </el-main>
        <el-aside>
        </el-aside>
    </el-container>
</template>

<script>
  import { getArticle, deleteArticle } from '../utils/api'
  import auth from '../utils/auth'

  export default {
    props: ['id'],
    data () {
      return {
        loading: false,
        dialogVisible: false,
        article: {}
      }
    },
    mounted: function () {
      this.loading = true
      getArticle(this.id)
        .then(article => {
          this.article = article
          this.loading = false
        })
        .catch(e => {
          console.error(e)
        })
    },
    computed: {
      loggedIn () {
        return auth.loggedIn()
      },
      baseUrl () {
        let baseUrl = 'https://kdb-api.tebe.ch/public/media/'
        if (process.env.NODE_ENV == 'development') {
          baseUrl = 'http://localhost:9999/media/'
        }
        return baseUrl
      }
    },
    methods: {
      deleteArticle () {
        this.dialogVisible = false
        this.$message({
          message: 'Artikel gelöscht',
          type: 'success'
        })
        this.$router.push('/articles')
        /*
        deleteArticle(this.id)
          .then(() => {
            this.$router.push('/articles')
          })
          .catch(error => {
            console.error(error.response.data)
          })
        */
      },
      markdownPostRender (value) {
        value = value.replace(new RegExp('src="/media/', 'g'), 'class="img-fluid" src="' + this.baseUrl)
        return value
      },
      markdownRendered () {
        Prism.highlightAll()
      }
    }
  }
</script>

<style scoped>

</style>

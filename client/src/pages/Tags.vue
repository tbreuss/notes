<template>
    <layout-default>
        <h4>Tags</h4>

        <div class="paging-text">
            <span v-show="tags.length>0">
                Zeige {{ tags.length }} Tags
            </span>
            <span class="paging-text__loading" v-show="loading">
                lade Tags
            </span>
        </div>

        <ul class="list-group">
            <a v-for="tag in tags" @click="toArticles(tag.name, $event)" href="#"
               class="list-group-item d-flex justify-content-between align-items-center" :key="tag.id">
                {{ tag.name}}
                <span class="badge badge-secondary badge-pill">{{ tag.frequency }}</span>
            </a>
        </ul>

        <div slot="aside">
            <div class="aside-sort">
                <h4 class="aside-sort__title">Sortieren nach</h4>
                <div class="custom-control custom-radio">
                    <input class="custom-control-input" @change="loadData" id="tags-sort-radio-1" type="radio" value="name" v-model="sort">
                    <label class="custom-control-label" for="tags-sort-radio-1">Tagname</label>
                </div>
                <div class="custom-control custom-radio">
                    <input class="custom-control-input" @change="loadData" id="tags-sort-radio-2" type="radio" value="frequency" v-model="sort">
                    <label class="custom-control-label" for="tags-sort-radio-2">Häufigkeit</label>
                </div>
                <div class="custom-control custom-radio">
                    <input class="custom-control-input" @change="loadData" id="tags-sort-radio-3" type="radio" value="changed" v-model="sort">
                    <label class="custom-control-label" for="tags-sort-radio-3">Letzter Änderung</label>
                </div>
                <div class="custom-control custom-radio">
                    <input class="custom-control-input" @change="loadData" id="tags-sort-radio-4" type="radio" value="created" v-model="sort">
                    <label class="custom-control-label" for="tags-sort-radio-4">Letzter Eintrag</label>
                </div>
            </div>
        </div>
    </layout-default>
</template>

<script>
  import http from '@/utils/http'
  import storage from '../utils/storage'
  export default {
    data () {
      return {
        tags: [],
        sort: storage.getTagsPageSort(),
        loading: false
      }
    },
    methods: {
      loadData () {
        this.loading = true
        let data = {}
        data.params = {
          sort: this.sort
        }
        http.get('tags', data, (tags) => {
          this.tags = tags
          this.loading = false
          storage.setTagsPageSort(this.sort)
        })
      },
      toArticles (tag, e) {
        e.preventDefault()
        storage.setArticlesTags([tag])
        this.$router.push('/articles')
      }
    },
    mounted () {
      this.loadData()
    }
  }

</script>

<style scoped>

</style>

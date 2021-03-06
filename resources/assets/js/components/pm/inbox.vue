<template>
  <li :class="{'open': isOpen}" v-on-clickaway="hideDropdown">
    <a @click.prevent="loadMessages" href="/User/Pm" role="button" aria-haspopup="true" aria-expanded="false">
      <span v-show="count > 0" class="badge">{{ count }}</span>

      <i class="fas fa-envelope fa-fw"></i>
    </a>

    <div ref="dropdown" v-show="isOpen" class="dropdown-alerts dropdown-menu right">
      <div class="dropdown-header">
        <a title="Przejdź do listy wiadomości" href="/User/Pm">Wiadomości</a>

        <a class="btn-write-message" href="/User/Pm/Submit">
          Wyślij wiadomość
        </a>
      </div>

      <perfect-scrollbar class="dropdown-modal" :options="{wheelPropagation: false}">
        <div v-if="messages === null" class="text-center">
          <i class="fas fa-spinner fa-spin"></i>
        </div>

        <vue-message v-for="message in messages" :message="message" :key="message.id"></vue-message>

        <div class="text-center" v-if="Array.isArray(messages) && messages.length === 0">Brak wiadomości prywatnych.</div>
      </perfect-scrollbar>
    </div>
  </li>
</template>

<script>
  import DesktopNotifications from '../../libs/notifications';
  import {default as ws} from '../../libs/realtime.js';
  import store from '../../store';
  import {default as PerfectScrollbar} from '../perfect-scrollbar';
  import {mixin as clickaway} from 'vue-clickaway';
  import VueMessage from './message-compact.vue';
  import {mapState} from "vuex";

  export default {
    mixins: [clickaway],
    components: {
      'perfect-scrollbar': PerfectScrollbar,
      'vue-message': VueMessage
    },
    store,
    data() {
      return {
        isOpen: false
      }
    },
    mounted() {
      this.listenForMessages();
    },
    methods: {
      loadMessages() {
        this.isOpen = !this.isOpen;

        if (this.$store.getters['inbox/isEmpty']) {
          this.$store.dispatch('inbox/get');
        }
      },

      hideDropdown() {
        this.isOpen = false;
      },

      listenForMessages() {
        ws.on('Coyote\\Events\\PmCreated', data => {
          this.$store.commit('inbox/increment');
          this.$store.commit('inbox/reset');

          this.isOpen = false;

          DesktopNotifications.doNotify(data.user.name, data.excerpt, data.url);
        });
      },
    },
    computed: mapState('inbox', ['messages', 'count'])
  };
</script>

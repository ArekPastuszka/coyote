<template>
  <ol ref="dropdown" class="auto-complete" style="width: 100%" v-show="isDropdownShown">
    <li v-for="(item, index) in items" :key="index" :class="{'hover': index === selectedIndex}" @click="selectItem" @mouseover="hoverItem(index)">

      <slot name="item" :item="item">
        <object :data="item.photo || '//'" type="image/png">
          <img src="/img/avatar.png" style="width: 100%">
        </object>

        <span>{{ item.name }}</span>

        <small v-if="item.group" class="label label-default">{{ item.group }}</small>
      </slot>
    </li>
  </ol>
</template>

<script>
  export default {
    props: {
      items: {
        type: Array,
        default: () => []
      }
    },
    data() {
      return {
        isDropdownShown: false,
        selectedIndex: -1
      }
    },
    mounted() {
      document.body.addEventListener('click', event => {
        if (!(this.$el === event.target || this.$el.contains(event.target))) {
          this.isDropdownShown = false;
        }
      });
    },
    methods: {
      goDown() {
        this.isDropdownShown = true;

        this.changeIndex(++this.selectedIndex);
      },

      goUp() {
        this.changeIndex(--this.selectedIndex);
      },

      changeIndex(index) {
        const length = this.items.length;

        if (length > 0) {
          if (index >= length) {
            index = 0;
          }
          else if (index < 0) {
            index = length - 1;
          }

          this.selectedIndex = index;
          this.adjustScrollbar();
        }
      },

      adjustScrollbar() {
        let dropdown = this.$refs['dropdown'];

        if (dropdown.children.length) {
          dropdown.scrollTop = this.hoverItem * dropdown.children[0].offsetHeight;
        }
      },

      selectItem() {
        const selected = this.getSelected();

        if (selected) {
          this.$emit('select', selected);
        }

        this.hideDropdown();
      },

      hoverItem(index) {
        this.selectedIndex = index;
      },

      toggleDropdown(flag) {
        this.isDropdownShown = flag;
      },

      hideDropdown() {
        this.isDropdownShown = false;
        this.selectedIndex = -1;
      },

      getSelected() {
        return this.selectedIndex > -1 ? this.items[this.selectedIndex] : null;
      }
    },
    watch: {
      items(items) {
        this.toggleDropdown(items.length);
      }
    }
  }
</script>

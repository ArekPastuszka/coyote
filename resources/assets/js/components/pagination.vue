<template>
    <ul v-if="totalPages > 1" class="pagination">
        <li v-if="currentPage > 1">
            <a href="javascript:" aria-label="Previous" @click.prevent="change(currentPage - 1)">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
        <li v-if="currentPage === 1 && totalPages > 0" class="disabled">
            <span>«</span>
        </li>

        <li v-for="element in slider" :class="{'active': element === currentPage, 'disabled': element === '...'}">
            <span v-if="element === currentPage">{{ element }}</span>
            <a v-else-if="element !== '...'" href="javascript:" @click.prevent="change(element)">{{ element }}</a>
            <span v-else>...</span>
        </li>

        <li v-if="currentPage < totalPages">
            <a href="javascript:" aria-label="Next" @click.prevent="change(currentPage + 1)">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
        <li v-if="totalPages > 0 && currentPage === totalPages" class="disabled">
            <span>»</span>
        </li>
    </ul>
</template>

<script>
    export default {
        props: {
            totalPages: {
                type: Number,
                required: true
            },
            currentPage: {
                type: Number,
                required: true
            },
            offset: {
                type: Number,
                default: 3
            }
        },
        computed: {
            slider: function () {
                if (!this.totalPages) {
                    return [];
                }

                const begin = [1, 2, '...'];

                let end = () => {
                    return ['...', this.totalPages - 1, this.totalPages];
                };

                if (this.totalPages < (this.offset * 2) + 6) {
                    return this.range(1, this.totalPages);
                }

                let window = this.offset * 2;

                if (this.currentPage <= window) {
                    return this.range(1, window + 2).concat(end());
                }

                if (this.currentPage > (this.totalPages - window)) {
                    return begin.concat(this.range(this.totalPages - (window + 2), this.totalPages));
                }

                let slider = begin.concat(this.range(this.currentPage - this.offset, this.currentPage + this.offset));
                return slider.concat(end());
            }
        },
        methods: {
            change: function(page) {
                // this.currentPage = page;

                this.$emit('change', page);
            },
            range: function(start, end) {
                let arr = [];

                for (let i = start; i <= end; i++) {
                    arr.push(i);
                }

                return arr;
            }
        }
    };
</script>

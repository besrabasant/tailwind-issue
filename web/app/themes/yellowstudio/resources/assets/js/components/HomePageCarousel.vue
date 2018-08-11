<template>
    <div class="w-full h-screen flex justify-center items-center">
       <h1>Home Page Carousel</h1>
    </div>
</template>

<script>
export default {
    mounted() {
        this.helloWorld();
    },
    data() {
        return {
            //
        }
    },
    methods: {
        helloWorld(){
            console.log('Hello, World!');
        }
    }
}
</script>

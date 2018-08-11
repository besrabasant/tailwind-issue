<template>
    <div>
       <h1>This is Vue JS Hello World Component</h1>
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

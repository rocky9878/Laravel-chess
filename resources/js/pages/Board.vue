<script setup lang="ts">
import board from '@/routes/board';
import { Piece, State } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

interface Props {
    board: number;
    pieces: Piece[];
    state: State;
}

const props = defineProps<Props>();

const boardRef = ref();
const selectedPiece = ref();
const selecedElement = ref();

const pieceMap = computed(() =>
    Object.fromEntries(props.pieces.map((p) => [`${p.x},${p.y}`, p]))
);

const form = useForm({
    from: [] as number[],
    to: [] as number[],
});

function getPiece(x: number, y: number): any {
    return pieceMap.value[`${x - 1},${y - 1}`];
}

function setPiece(x: number, y: number, event: Event): any {
    event.preventDefault();
    if (pieceMap.value[`${x - 1},${y - 1}`].colour !== props.state['toMove']) return;

    selecedElement.value = event.target;

    selectedPiece.value = pieceMap.value[`${x - 1},${y - 1}`];
}

function makeMove(x: number, y: number, piece: Piece) {
    selectedPiece.value = null;
    form.from = [piece.x, piece.y];
    form.to = [x - 1, y - 1];
    form.submit(board.update(props.board));
}

</script>

<template>
    <div class="mt-20" ref="boardRef" @click="selecedElement = null; selectedPiece = null">
        <div v-for="y in 8" class="max-w-160 flex flex-wrap mx-auto">
            <div v-for="x in 8" class="size-20 bg-gray-100 text-black flex justify-center items-center relative" :class="{ 'bg-yellow-500' :(x + y % 2) % 2 }">
                <img v-if="getPiece(x, y)" :src="`/pieces/${getPiece(x, y)!.colour.charAt(0)}_${getPiece(x, y)!.type}.svg`" class="size-9/10" @click.stop="setPiece(x, y, $event)" />
                <div v-if="selectedPiece && selectedPiece.legalMoves.filter((move: Array<Number>) => move[0] === x - 1 && move[1] === y - 1).length !== 0"
                    class="rounded-full bg-gray-700 opacity-50 size-10 absolute"
                    :class="{'bg-transparent! opacity-100 size-18 border-4 border-red-500': getPiece(x, y)}"
                    @click.stop="makeMove(x, y, selectedPiece)">
                </div>
            </div>
        </div>
    </div>
</template>

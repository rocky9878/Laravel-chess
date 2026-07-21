<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import board from '@/routes/board';
import { Piece } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Props {
    board: number;
    player_colour: string;
    pieces: Piece[];
    state: 'active' | 'white' | 'black' | 'stalemate' | 'Threefold repition' | '50 move rule' | 'Insufficient material';
    toMove: 'white' | 'black';
    score: number;
}

interface Promoting {
    from: number[];
    to: number[];
    piece: Piece;
}

const props = defineProps<Props>();

const selectedPiece = ref();
const promoting = ref<Promoting|null>();
const promotionStyles = ref('');

const pieceMap = computed(() =>
    Object.fromEntries(props.pieces.map((p) => [`${p.x},${p.y}`, p]))
);

const form = useForm({
    from: [] as number[],
    to: [] as number[],
    promotion: '' as string,
});

function getPiece(x: number, y: number): any {
    return pieceMap.value[`${x - 1},${y - 1}`];
}

function setPiece(x: number, y: number, event: Event): any {
    if (promoting.value) {
        return;
    }

    event.preventDefault();
    if (pieceMap.value[`${x - 1},${y - 1}`].colour !== props.toMove) return;

    // if (pieceMap.value[`${x - 1},${y - 1}`].colour !== props.player_colour) return;

    selectedPiece.value = pieceMap.value[`${x - 1},${y - 1}`];
}

function makeMove(x: number, y: number, piece: Piece, promotingTo: null|string = null) {
    if (promoting.value && !promotingTo) {
        return;
    }

    if(piece.type === 'pawn' && (y === 1 || y === 8) && !promotingTo) {
        // show promotion menu
        const top = piece.colour === 'white' ? 0 : 20;

        promoting.value = {'from': [piece.x, piece.y], 'to': [x, y], 'piece': piece};
        promotionStyles.value = 'top: '+top+'rem; left: '+((x-1)*5)+'rem;';

        return;
    }

    form.promotion = promotingTo ?? '';

    selectedPiece.value = null;
    form.from = [piece.x, piece.y];
    form.to = [x - 1, y - 1];

    promoting.value = null;

    form.submit(board.update(props.board));
}

function deselectPiece() {
    if (promoting.value) {
        return;
    }
    selectedPiece.value = null;
}

</script>

<template>
    <div class="min-h-screen flex items-center justify-center" @click="deselectPiece()">
        <div class="text-6xl mr-8">{{ score }}</div>
        <div class="w-fit relative">
            <div v-for="y in 8" class="max-w-160 flex flex-wrap">
                <div v-for="x in 8" class="size-20 bg-gray-100 text-black flex justify-center items-center relative" :class="{ 'bg-yellow-500' :(x + y % 2) % 2 }">
                    <img v-if="getPiece(x, y)" :src="`/pieces/${getPiece(x, y)!.colour.charAt(0)}_${getPiece(x, y)!.type}.svg`" class="size-9/10" :class="{'cursor-pointer': !promoting}" @click.stop="setPiece(x, y, $event)" />
                    <div v-if="selectedPiece && selectedPiece.legalMoves.filter((move: Array<Number>) => move[0] === x - 1 && move[1] === y - 1).length !== 0"
                        class="rounded-full bg-gray-700 opacity-50 size-10 absolute cursor-pointer"
                        :class="{'bg-transparent! opacity-100 size-18 border-4 border-red-500': getPiece(x, y)}"
                        @click.stop="makeMove(x, y, selectedPiece)">
                    </div>
                </div>
            </div>
            <div v-if="promoting" class="w-20 bg-gray-300 shadow absolute" :style="promotionStyles">
                <img v-for="pieceType in ['queen', 'knight', 'rook', 'bishop']" :src="`/pieces/${promoting.piece.colour.charAt(0)}_${pieceType}.svg`" class="size-20 cursor-pointer" @click.stop="makeMove(promoting.to[0], promoting.to[1], promoting.piece, pieceType)" />
            </div>
            <div v-if="state !== 'active'" class="absolute rounded shadow-2xl bg-gray-600 top-1/2 left-1/2 -translate-1/2">
                <div class="capitalize text-center text-4xl font-extrabold px-12 py-8 rounded-t" :class="{ 'bg-green-500': player_colour === state }">{{ state === player_colour ? 'Checkmate' : state }}</div>
                <div class="mx-auto w-fit my-4">
                    <Button class="cursor-pointer">
                        <Link :href="home()">new match</Link>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

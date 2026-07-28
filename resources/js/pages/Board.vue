<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import board from '@/routes/board';
import type { Piece } from '@/types';

const files = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];

interface Props {
    board: number;
    player_colour: 'white' | 'black';
    pieces: Piece[];
    state:
        | 'active'
        | 'white'
        | 'black'
        | 'stalemate'
        | 'Threefold repition'
        | '50 move rule'
        | 'Insufficient material';
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
const promoting = ref<Promoting | null>();

const pieceMap = computed(() =>
    Object.fromEntries(props.pieces.map((p) => [`${p.x},${p.y}`, p])),
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

    if (pieceMap.value[`${x - 1},${y - 1}`].colour !== props.toMove) {
        return;
    }

    if (pieceMap.value[`${x - 1},${y - 1}`].colour !== props.player_colour) {
        return;
    }

    selectedPiece.value = pieceMap.value[`${x - 1},${y - 1}`];
}

function makeMove(
    x: number,
    y: number,
    piece: Piece,
    promotingTo: null | string = null,
) {
    if (promoting.value && !promotingTo) {
        return;
    }

    if (piece.type === 'pawn' && (y === 1 || y === 8) && !promotingTo) {
        promoting.value = {
            from: [piece.x, piece.y],
            to: [x, y],
            piece: piece,
        };

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

const computerThinking = ref(false);

watch(
    () => [props.toMove, props.state] as const,
    ([toMove, state]) => {
        if (
            state !== 'active' ||
            toMove === props.player_colour ||
            computerThinking.value
        ) {
            return;
        }

        computerThinking.value = true;

        router.post(
            board.computerMove(props.board).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    computerThinking.value = false;
                },
            },
        );
    },
    { immediate: true },
);
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center gap-8"
        @click="deselectPiece()"
    >
        <Card class="items-center gap-3 px-8 py-6">
            <div class="text-sm text-muted-foreground">Score</div>
            <div class="text-6xl font-semibold tabular-nums">{{ score }}</div>
            <Badge
                :variant="toMove === player_colour ? 'default' : 'secondary'"
                class="capitalize"
            >
                {{ toMove }} to move
            </Badge>
            <div
                v-if="computerThinking"
                class="flex items-center gap-2 text-sm text-muted-foreground italic"
            >
                <Spinner class="size-4" />
                thinking…
            </div>
        </Card>

        <div class="relative w-fit">
            <div class="flex items-start">
                <div
                    class="grid grid-cols-8 overflow-hidden rounded-md border shadow-md"
                    style="
                        background-image: conic-gradient(
                            from 0deg,
                            var(--board-dark) 0deg 90deg,
                            var(--board-light) 90deg 180deg,
                            var(--board-dark) 180deg 270deg,
                            var(--board-light) 270deg 360deg
                        );
                        background-size: 10rem 10rem;
                    "
                >
                    <template v-for="y in 8" :key="y">
                        <div
                            v-for="x in 8"
                            :key="x"
                            class="relative flex size-20 items-center justify-center text-black"
                        >
                            <img
                                v-if="getPiece(x, y)"
                                :src="`/pieces/${getPiece(x, y)!.colour.charAt(0)}_${getPiece(x, y)!.type}.svg`"
                                class="size-9/10"
                                :class="{ 'cursor-pointer': !promoting }"
                                @click.stop="setPiece(x, y, $event)"
                            />
                            <div
                                v-if="
                                    selectedPiece &&
                                    selectedPiece.legalMoves.filter(
                                        (move: Array<Number>) =>
                                            move[0] === x - 1 &&
                                            move[1] === y - 1,
                                    ).length !== 0
                                "
                                class="absolute size-10 cursor-pointer rounded-full bg-board-highlight opacity-70"
                                :class="{
                                    'size-18 border-4 border-board-capture bg-transparent! opacity-100':
                                        getPiece(x, y),
                                }"
                                @click.stop="makeMove(x, y, selectedPiece)"
                            ></div>
                        </div>
                    </template>
                </div>
                <div class="ml-1 flex flex-col justify-around py-1">
                    <span
                        v-for="rank in 8"
                        :key="rank"
                        class="flex h-20 items-center text-xs leading-none text-muted-foreground"
                        >{{ 9 - rank }}</span
                    >
                </div>
            </div>
            <div class="mt-1 grid w-160 grid-cols-8">
                <span
                    v-for="file in files"
                    :key="file"
                    class="text-center text-xs text-muted-foreground"
                    >{{ file }}</span
                >
            </div>
        </div>

        <Dialog :open="!!promoting">
            <DialogContent
                v-if="promoting"
                class="sm:max-w-fit"
                :show-close-button="false"
                @escape-key-down.prevent
                @pointer-down-outside.prevent
            >
                <DialogTitle>Choose promotion</DialogTitle>
                <div class="flex gap-2">
                    <Button
                        v-for="pieceType in [
                            'queen',
                            'knight',
                            'rook',
                            'bishop',
                        ]"
                        :key="pieceType"
                        variant="outline"
                        class="size-20 p-2"
                        @click.stop="
                            makeMove(
                                promoting.to[0],
                                promoting.to[1],
                                promoting.piece,
                                pieceType,
                            )
                        "
                    >
                        <img
                            :src="`/pieces/${promoting.piece.colour.charAt(0)}_${pieceType}.svg`"
                            class="size-full"
                        />
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog :open="state !== 'active'">
            <DialogContent
                class="text-center sm:max-w-fit"
                :show-close-button="false"
                @escape-key-down.prevent
                @pointer-down-outside.prevent
            >
                <DialogTitle class="text-4xl font-extrabold capitalize">
                    <Badge
                        v-if="player_colour === state"
                        variant="default"
                        class="px-3 py-1 text-base"
                        >Checkmate — you win</Badge
                    >
                    <span v-else>{{ state }}</span>
                </DialogTitle>
                <div class="mx-auto mt-2 w-fit">
                    <Button class="cursor-pointer">
                        <Link :href="home()">new match</Link>
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

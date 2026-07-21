export * from './auth';
export * from './navigation';
export * from './ui';

export interface Piece {
    type: string;
    x: number;
    y: number;
    hasMoved: boolean;
    colour: 'black' | 'white';
    legalMoves: Array<[number, number]>;
}

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

export interface State {
    state: 'active' | 'white' | 'black' | 'stalemate' | 'Threefold repition' | '50 move rule' | 'Insufficient material';
    toMove: 'white' | 'black'
    halfmove: number;
    fullmove: number;
}
